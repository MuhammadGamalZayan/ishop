<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Exceptions\InsufficientStockException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\ShippingArea;
use App\Models\User;
use App\Services\Inventory;
use App\Services\Payments\StripePayments;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Turns the signed-in customer's cart into an order. Every rule from the
 * design doc's concurrency table is enforced here:
 *
 * - Double click / resubmit: the idempotency key (made when the checkout
 *   page renders) is UNIQUE on orders; a repeat returns the same order.
 * - Two tabs: the cart row is locked and emptied in the same transaction;
 *   the second tab finds an empty cart.
 * - Last unit: variants are locked FOR UPDATE in ascending id order and
 *   stock is deducted through Inventory before commit (DB CHECK behind it).
 * - Coupon over-use: the coupon row is locked and its redemptions counted
 *   inside the transaction.
 * - Price changes: totals are recomputed from the locked rows; if they
 *   differ from what the customer saw, nothing is saved and the new quote
 *   is returned to show.
 * - Deadlocks: the transaction retries up to 3 times.
 */
class PlaceOrder
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly Inventory $inventory,
    ) {}

    public function handle(User $user, CheckoutData $data): Order
    {
        $available = $data->paymentMethod === PaymentMethod::CashOnDelivery
            || ($data->paymentMethod === PaymentMethod::Stripe && StripePayments::enabled());

        if (! $available) {
            // Paymob arrives later with its own step (payment attempt + webhook).
            throw CheckoutException::paymentMethod();
        }

        if ($existing = $this->existing($user, $data->idempotencyKey)) {
            return $existing;
        }

        try {
            $order = DB::transaction(fn (): Order => $this->place($user, $data), attempts: 3);
        } catch (UniqueConstraintViolationException $e) {
            // A parallel request with the same key committed first.
            return $this->existing($user, $data->idempotencyKey) ?? throw $e;
        }

        if ($order->wasRecentlyCreated) {
            OrderPlaced::dispatch($order);
        }

        return $order;
    }

    private function place(User $user, CheckoutData $data): Order
    {
        if ($existing = $this->existing($user, $data->idempotencyKey, lock: true)) {
            return $existing;
        }

        // 1. The cart: locked, so a second tab waits here and then finds it empty.
        $cart = Cart::query()->where('user_id', $user->getKey())->lockForUpdate()->first();
        $lines = $cart?->items()->get() ?? collect();

        if ($lines->isEmpty()) {
            throw CheckoutException::emptyCart();
        }

        // 2. The variants, locked in id order (the same order Inventory uses).
        $variants = ProductVariant::query()
            ->with(['product', 'attributeValues.productAttribute'])
            ->whereKey($lines->pluck('product_variant_id')->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($lines->sortBy('product_variant_id') as $line) {
            $variant = $variants->get($line->product_variant_id);
            $name = $variant?->product?->name ?? __('checkout.errors.a_product');

            if (! $variant || ! $variant->is_active || ! $variant->product?->isPublished()) {
                throw CheckoutException::unavailable($name);
            }

            if ($variant->track_inventory && $variant->stock_quantity < $line->quantity) {
                throw CheckoutException::outOfStock($name, $variant->stock_quantity);
            }

            $items[] = ['variant' => $variant, 'quantity' => $line->quantity];
        }

        // 3. Where it goes.
        [$addressFields, $area] = $this->address($user, $data);

        // 4. The coupon, locked so its usage counts can't move under us.
        $coupon = null;
        $code = $data->couponCode ?? ($cart->coupon_id ? Coupon::query()->whereKey($cart->coupon_id)->value('code') : null);
        if ($code !== null && $code !== '') {
            $coupon = Coupon::query()->where('code', mb_strtoupper(trim($code)))->lockForUpdate()->first()
                ?? throw CouponException::because('not_found');
        }

        // 5. Price it from the locked rows.
        $quote = $this->pricing->quote($items, $coupon, $area, $user, lockingReads: true);

        if ($quote->couponError !== null) {
            throw new CouponException($quote->couponError);
        }

        if ($data->expectedTotal !== null && $data->expectedTotal !== $quote->grandTotal) {
            throw CheckoutException::priceChanged($quote);
        }

        // 6. Write the order and its snapshots.
        $order = new Order;
        $order->forceFill([
            'number' => OrderNumber::generate(),
            'user_id' => $user->getKey(),
            'idempotency_key' => $data->idempotencyKey,
            'customer_name' => trim($addressFields['first_name'].' '.$addressFields['last_name']),
            'customer_email' => $user->email,
            'customer_phone' => $addressFields['phone'],
            'status' => OrderStatus::Pending,
            'payment_method' => $data->paymentMethod,
            'payment_status' => PaymentStatus::Unpaid,
            'currency' => config('ishop.currency'),
            'subtotal' => $quote->subtotal,
            'discount_total' => $quote->discountTotal,
            'shipping_total' => $quote->shippingTotal,
            'tax_total' => $quote->taxTotal,
            'grand_total' => $quote->grandTotal,
            'coupon_id' => $quote->coupon?->getKey(),
            'coupon_code' => $quote->coupon?->code,
            'customer_note' => filled($data->customerNote) ? mb_substr(trim($data->customerNote), 0, 1000) : null,
            'placed_at' => now(),
            // Card orders hold their stock only this long; then OrdersExpireUnpaid cancels them.
            'payment_expires_at' => $data->paymentMethod->isOnline() ? now()->addMinutes((int) config('ishop.payment_timeout_minutes')) : null,
        ])->save();

        foreach ($quote->lines as $line) {
            (new OrderItem)->forceFill([
                'order_id' => $order->getKey(),
                'product_variant_id' => $line->variant->getKey(),
                'product_name' => $line->variant->product->getTranslations('name'),
                'sku' => $line->variant->sku,
                'options' => $line->options ?: null,
                'unit_price' => $line->unitPrice,
                'quantity' => $line->quantity,
                'discount_amount' => $line->discount,
                'tax_amount' => $line->tax,
                'line_total' => $line->lineTotal,
            ])->save();
        }

        (new OrderAddress)->forceFill([
            'order_id' => $order->getKey(),
            'first_name' => $addressFields['first_name'],
            'last_name' => $addressFields['last_name'],
            'phone' => $addressFields['phone'],
            'governorate_id' => $area->governorate_id,
            'shipping_area_id' => $area->getKey(),
            'governorate_name' => $area->governorate->getTranslations('name'),
            'area_name' => $area->getTranslations('name'),
            'street' => $addressFields['street'],
            'building' => $addressFields['building'],
            'floor' => $addressFields['floor'] ?? null,
            'apartment' => $addressFields['apartment'] ?? null,
            'landmark' => $addressFields['landmark'] ?? null,
        ])->save();

        (new OrderStatusHistory)->forceFill([
            'order_id' => $order->getKey(),
            'from_status' => null,
            'to_status' => OrderStatus::Pending,
            'user_id' => $user->getKey(),
        ])->save();

        if ($quote->coupon) {
            CouponRedemption::create([
                'coupon_id' => $quote->coupon->getKey(),
                'order_id' => $order->getKey(),
                'user_id' => $user->getKey(),
                'discount_amount' => $quote->discountTotal,
            ]);
        }

        // 7. Take the stock (re-checked under the same locks; throws on any shortfall).
        try {
            $this->inventory->deduct(
                collect($quote->lines)->mapWithKeys(fn (QuoteLine $l): array => [$l->variant->getKey() => $l->quantity])->all(),
                $order,
            );
        } catch (InsufficientStockException $e) {
            throw CheckoutException::outOfStock($variants->get($e->variantId)?->product?->name ?? $e->sku, $e->available);
        }

        // 8. Empty the cart and keep the address if asked.
        $cart->items()->delete();
        $cart->forceFill(['coupon_id' => null])->save();

        if ($data->addressId === null && $data->saveAddress) {
            $user->addresses()->create($addressFields + ['is_default' => ! $user->addresses()->exists()]);
        }

        return $order;
    }

    /**
     * @return array{0: array<string, mixed>, 1: ShippingArea}
     */
    private function address(User $user, CheckoutData $data): array
    {
        if ($data->addressId !== null) {
            /** @var Address|null $saved */
            $saved = $user->addresses()->whereKey($data->addressId)->first();
            $fields = $saved?->only(['first_name', 'last_name', 'phone', 'governorate_id', 'shipping_area_id', 'street', 'building', 'floor', 'apartment', 'landmark'])
                ?? throw CheckoutException::address();
        } elseif ($data->address !== null) {
            $fields = $data->address;
        } else {
            throw CheckoutException::address();
        }

        // Only areas we deliver to right now, inside a governorate we deliver to.
        $area = ShippingArea::query()
            ->with('governorate')
            ->whereKey($fields['shipping_area_id'] ?? 0)
            ->where('is_active', true)
            ->whereHas('governorate', fn ($q) => $q->where('is_active', true))
            ->first() ?? throw CheckoutException::shippingArea();

        $fields['governorate_id'] = $area->governorate_id;
        $fields['shipping_area_id'] = $area->getKey();

        return [$fields, $area];
    }

    /** An order already placed with this key, if it belongs to this customer. */
    private function existing(User $user, string $key, bool $lock = false): ?Order
    {
        $order = Order::query()->where('idempotency_key', $key)->when($lock, fn ($q) => $q->lockForUpdate())->first();

        if ($order && $order->user_id !== $user->getKey()) {
            throw CheckoutException::keyReused();
        }

        return $order;
    }
}

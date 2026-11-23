<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\ShippingArea;
use App\Models\User;
use App\Services\Cart\CartIssue;
use App\Services\Cart\CartReview;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutData;
use App\Services\Checkout\CouponRules;
use App\Services\Checkout\PlaceOrder;
use App\Services\Checkout\Pricing;
use App\Support\Storefront\CartDrawer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout page (approved mockup: Information → Payment).
 * Prices shown here are a preview; PlaceOrder recomputes everything under
 * row locks and refuses if the total the customer saw has changed.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartReview $review,
        private readonly Pricing $pricing,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cart = $this->carts->current();

        if (! $cart || ! $cart->items()->exists()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('shop.checkout.cart_empty')]);

            return redirect()->route('shop.home');
        }

        $addresses = $user->addresses()->with(['governorate', 'shippingArea'])->latest('is_default')->latest('id')->get()
            ->filter(fn (Address $a): bool => $a->shippingArea?->is_active && $a->governorate?->is_active);

        $areaId = $request->integer('area') ?: ($addresses->first()?->shipping_area_id);
        $area = $areaId ? ShippingArea::query()->with('governorate')->whereKey($areaId)->where('is_active', true)->first() : null;

        return Inertia::render('shop/checkout', [
            // A new key per page view: double clicks and resubmits of THIS page make one order.
            'idempotencyKey' => (string) Str::uuid(),
            'email' => $user->email,
            'lines' => app(CartDrawer::class)->data()['lines'],
            'quote' => $this->quote($cart, $area, $user),
            'addresses' => $addresses->map(fn (Address $a): array => [
                'id' => $a->getKey(),
                'label' => $a->label,
                'is_default' => $a->is_default,
                'shipping_area_id' => $a->shipping_area_id,
                'text' => $this->addressText($a),
            ])->values(),
            'governorates' => Governorate::query()->active()
                ->with(['shippingAreas' => fn ($q) => $q->active()])
                ->get()
                ->filter(fn (Governorate $g): bool => $g->shippingAreas->isNotEmpty())
                ->map(fn (Governorate $g): array => [
                    'id' => $g->getKey(),
                    'name' => $g->name,
                    'areas' => $g->shippingAreas->map(fn (ShippingArea $a): array => ['id' => $a->getKey(), 'name' => $a->name, 'price' => $a->shipping_price])->values(),
                ])->values(),
            'areaId' => $area?->getKey(),
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        try {
            $order = $placeOrder->handle($user, new CheckoutData(
                idempotencyKey: $data['idempotency_key'],
                paymentMethod: PaymentMethod::from($data['payment_method']),
                addressId: $data['address_id'] ?? null,
                address: $data['address'] ?? null,
                saveAddress: (bool) ($data['save_address'] ?? false),
                customerNote: $data['customer_note'] ?? null,
                expectedTotal: (int) $data['expected_total'],
            ));
        } catch (CheckoutException|CouponException $e) {
            // Page reloads with fresh lines, warnings and totals; the reason shows as a toast.
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.checkout.placed', ['number' => $order->number])]);

        return redirect()->route('shop.account.order', ['number' => $order->number]);
    }

    public function applyCoupon(Request $request, CouponRules $rules): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:50']])['code'];
        $cart = $this->carts->current() ?? throw ValidationException::withMessages(['code' => __('checkout.errors.empty_cart')]);

        try {
            $coupon = $rules->find($code);
            $rules->assertUsable($coupon, $this->subtotal($cart), $request->user());
        } catch (CouponException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $cart->forceFill(['coupon_id' => $coupon->getKey()])->save();

        return back();
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->carts->current()?->forceFill(['coupon_id' => null])->save();

        return back();
    }

    /** Preview totals from the lines that can be bought (lines with issues are left out). */
    private function quote(Cart $cart, ?ShippingArea $area, User $user): array
    {
        $items = $this->review->lines($cart)
            ->filter(fn (array $l): bool => $l['issue'] === null || $l['issue'] === CartIssue::OnlyLeft)
            ->map(fn (array $l): array => ['variant' => $l['variant'], 'quantity' => $l['item']->quantity])
            ->values()
            ->all();

        $coupon = $cart->coupon_id ? Coupon::query()->find($cart->coupon_id) : null;
        $quote = $this->pricing->quote($items, $coupon, $area, $user);

        return [
            ...$quote->toArray(),
            'item_count' => $quote->itemCount(),
            'has_area' => $area !== null,
        ];
    }

    private function subtotal(Cart $cart): int
    {
        return (int) $this->review->lines($cart)
            ->filter(fn (array $l): bool => $l['issue'] === null)
            ->sum(fn (array $l): int => $l['variant']->price * $l['item']->quantity);
    }

    private function addressText(Address $a): string
    {
        return collect([
            trim($a->first_name.' '.$a->last_name),
            trim($a->street.', '.__('shop.address.building_short', ['value' => $a->building])
                .($a->floor ? ', '.__('shop.address.floor_short', ['value' => $a->floor]) : '')
                .($a->apartment ? ', '.__('shop.address.apartment_short', ['value' => $a->apartment]) : '')),
            $a->shippingArea?->name.', '.$a->governorate?->name,
            $a->phone,
        ])->filter()->implode(' · ');
    }
}

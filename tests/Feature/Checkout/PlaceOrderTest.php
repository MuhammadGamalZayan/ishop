<?php

namespace Tests\Feature\Checkout;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Events\OrderPlaced;
use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Checkout\PlaceOrder;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Support\Facades\Event;

class PlaceOrderTest extends CheckoutTestCase
{
    private function place(array $overrides = [], ?User $user = null): Order
    {
        return app(PlaceOrder::class)->handle($user ?? $this->customer, $this->data($overrides));
    }

    public function test_a_cod_order_is_placed_with_snapshots_stock_and_an_empty_cart(): void
    {
        Event::fake([OrderPlaced::class]);
        $a = $this->variant(price: 114000, stock: 5);
        $b = $this->variant(price: 57000, stock: 2);
        $this->cart([$a->id => 2, $b->id => 1]);

        $order = $this->place(['customerNote' => '  Call before coming  ', 'saveAddress' => true]);

        $this->assertMatchesRegularExpression('/^IS-\d{6}-[2-9A-HJKMNP-Z]{5}$/', $order->number);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentMethod::CashOnDelivery, $order->payment_method);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame([285000, 0, 6000, 291000], [$order->subtotal, $order->discount_total, $order->shipping_total, $order->grand_total]);
        $this->assertSame(35737, $order->tax_total);
        $this->assertSame('Sara Kamal', $order->customer_name);
        $this->assertSame('01001234567', $order->customer_phone);
        $this->assertSame('Call before coming', $order->customer_note);

        // Line snapshots survive later product edits.
        $item = $order->items()->where('product_variant_id', $a->id)->sole();
        $a->product->update(['name' => ['en' => 'Renamed', 'ar' => 'اسم جديد']]);
        $this->assertNotSame('Renamed', $item->fresh()->getTranslation('product_name', 'en'));
        $this->assertSame(228000, $item->line_total);

        $address = $order->address;
        $this->assertSame('سموحة', $address->getTranslation('area_name', 'ar'));
        $this->assertSame('Alexandria', $address->getTranslation('governorate_name', 'en'));

        // Stock taken, linked to the order.
        $this->assertSame([3, 1], [$a->fresh()->stock_quantity, $b->fresh()->stock_quantity]);
        $this->assertSame(2, StockMovement::query()->where('type', StockMovementType::Sale)->where('reference_id', $order->id)->count());

        $this->assertSame(0, $this->customer->cart->items()->count(), 'cart emptied');
        $this->assertSame(1, $this->customer->addresses()->count(), 'address saved');
        $this->assertTrue($this->customer->addresses()->first()->is_default);
        $this->assertSame([null], $order->statusHistory()->pluck('from_status')->all());
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_resubmitting_the_same_checkout_returns_the_same_order(): void
    {
        Event::fake([OrderPlaced::class]);
        $variant = $this->variant(stock: 5);
        $this->cart([$variant->id => 1]);
        $data = $this->data();

        $first = app(PlaceOrder::class)->handle($this->customer, $data);
        $second = app(PlaceOrder::class)->handle($this->customer, $data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(4, $variant->fresh()->stock_quantity, 'stock taken once');
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_another_customer_cannot_reuse_an_idempotency_key(): void
    {
        $variant = $this->variant();
        $this->cart([$variant->id => 1]);
        $data = $this->data();
        app(PlaceOrder::class)->handle($this->customer, $data);

        $other = User::factory()->create();
        $this->cart([$variant->id => 1], $other);

        $this->expectException(CheckoutException::class);
        app(PlaceOrder::class)->handle($other, $data);
    }

    public function test_a_second_tab_finds_the_cart_empty(): void
    {
        $variant = $this->variant(stock: 5);
        $this->cart([$variant->id => 1]);

        $this->place();

        $this->expectExceptionMessage('Your cart is empty');
        $this->place(); // new key, same cart
    }

    public function test_a_shortfall_saves_nothing(): void
    {
        $plenty = $this->variant(stock: 5);
        $short = $this->variant(stock: 1);
        $this->cart([$plenty->id => 2, $short->id => 2]);

        try {
            $this->place();
            $this->fail('Expected CheckoutException');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString('Only 1 of', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
        $this->assertSame([5, 1], [$plenty->fresh()->stock_quantity, $short->fresh()->stock_quantity]);
        $this->assertSame(2, $this->customer->cart->items()->count(), 'cart untouched');
    }

    public function test_a_price_change_is_shown_before_anything_is_saved(): void
    {
        $variant = $this->variant(price: 100000);
        $this->cart([$variant->id => 1]);
        $seen = 100000 + 6000;

        $variant->update(['price' => 120000]);

        try {
            $this->place(['expectedTotal' => $seen]);
            $this->fail('Expected price change');
        } catch (CheckoutException $e) {
            $this->assertSame(126000, $e->quote?->grandTotal);
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(126000, $this->place(['expectedTotal' => 126000])->grand_total);
    }

    public function test_unavailable_products_and_areas_are_refused(): void
    {
        $variant = $this->variant();
        $this->cart([$variant->id => 1]);

        $this->area->update(['is_active' => false]);
        try {
            $this->place();
            $this->fail('inactive area');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString("don't deliver", $e->getMessage());
        }

        $this->area->update(['is_active' => true]);
        $variant->product->update(['status' => 'draft']);
        $this->expectExceptionMessage('no longer available');
        $this->place();
    }

    public function test_someone_elses_saved_address_cannot_be_used(): void
    {
        $other = User::factory()->create();
        $theirs = $other->addresses()->create([
            'first_name' => 'X', 'last_name' => 'Y', 'phone' => '01000000000', 'governorate_id' => $this->area->governorate_id,
            'shipping_area_id' => $this->area->id, 'street' => 'S', 'building' => '1',
        ]);
        $variant = $this->variant();
        $this->cart([$variant->id => 1]);

        $this->expectExceptionMessage('Choose a delivery address');
        $this->place(['addressId' => $theirs->id, 'address' => null]);
    }

    public function test_coupon_is_redeemed_and_its_limits_hold(): void
    {
        $coupon = Coupon::create(['code' => 'ONCE', 'type' => CouponType::Fixed, 'value' => 10000, 'usage_limit' => 1]);
        $variant = $this->variant(stock: 5);

        $this->cart([$variant->id => 1]);
        $order = $this->place(['couponCode' => 'once']);
        $this->assertSame(10000, $order->discount_total);
        $this->assertSame('ONCE', $order->coupon_code);
        $this->assertSame(1, CouponRedemption::count());

        $other = User::factory()->create();
        $this->cart([$variant->id => 1], $other);
        $this->expectException(CouponException::class);
        $this->place(['couponCode' => 'ONCE'], $other);
    }

    public function test_per_customer_coupon_limit(): void
    {
        Coupon::create(['code' => 'WELCOME', 'type' => CouponType::Percentage, 'value' => 1000, 'usage_limit_per_user' => 1]);
        $variant = $this->variant(stock: 5);

        $this->cart([$variant->id => 1]);
        $this->place(['couponCode' => 'WELCOME']);

        $this->cart([$variant->id => 1]);
        $this->expectExceptionMessage('already used this code');
        $this->place(['couponCode' => 'WELCOME']);
    }

    public function test_only_cash_on_delivery_for_now(): void
    {
        $this->cart([$this->variant()->id => 1]);

        $this->expectException(CheckoutException::class);
        $this->place(['paymentMethod' => PaymentMethod::Paymob]);
    }

    // ---- Status changes -----------------------------------------------------

    public function test_cancelling_restocks_and_frees_the_coupon(): void
    {
        Coupon::create(['code' => 'ONCE', 'type' => CouponType::Fixed, 'value' => 1000, 'usage_limit' => 1]);
        $variant = $this->variant(stock: 3);
        $this->cart([$variant->id => 2]);
        $order = $this->place(['couponCode' => 'ONCE']);
        $admin = User::factory()->create();

        app(OrderWorkflow::class)->transition($order, OrderStatus::Cancelled, $admin, 'Customer asked by phone');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
        $this->assertSame(0, CouponRedemption::count());
        $last = $order->statusHistory()->latest('id')->first();
        $this->assertSame([OrderStatus::Pending->value, $admin->id, 'Customer asked by phone'], [$last->from_status->value ?? $last->from_status, $last->user_id, $last->comment]);
    }

    public function test_cod_is_paid_on_delivery_and_jumps_are_refused(): void
    {
        $variant = $this->variant();
        $this->cart([$variant->id => 1]);
        $order = $this->place();
        $flow = app(OrderWorkflow::class);

        try {
            $flow->transition($order, OrderStatus::Delivered);
            $this->fail('pending → delivered must be refused');
        } catch (InvalidOrderTransitionException) {
            // expected
        }

        foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
            $flow->transition($order, $status);
        }

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $this->expectException(InvalidOrderTransitionException::class);
        $flow->transition($order, OrderStatus::Cancelled);
    }
}

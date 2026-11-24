<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\PlaceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\Feature\Checkout\CheckoutTestCase;

/** The storefront pages around checkout and My account, through HTTP like a browser. */
class AccountAndCheckoutPageTest extends CheckoutTestCase
{
    private function address(): array
    {
        return [
            'first_name' => 'Sara', 'last_name' => 'Kamal', 'phone' => '0100 123 4567',
            'shipping_area_id' => $this->area->id, 'street' => '12 Fawzy Moaz St', 'building' => '7',
        ];
    }

    public function test_dashboard_sends_staff_to_admin_and_customers_to_their_orders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->withTwoFactor()->create();
        $staff->assignRole('admin');

        $this->actingAs($staff)->get('/dashboard')->assertRedirect('/admin');
        $this->actingAs($this->customer)->get('/dashboard')->assertRedirect(route('shop.account.orders', ['locale' => 'en']));
        auth()->logout();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_checkout_needs_sign_in_and_a_cart(): void
    {
        $this->get('/en/checkout')->assertRedirect(route('login'));
        $this->actingAs($this->customer)->get('/en/checkout')->assertRedirect(route('shop.home', ['locale' => 'en']));

        $this->cart([$this->variant()->id => 1]);
        $this->actingAs($this->customer)->get('/en/checkout')->assertOk();
    }

    public function test_placing_an_order_from_the_page(): void
    {
        $variant = $this->variant(price: 100000, stock: 3);
        $this->cart([$variant->id => 2]);

        $response = $this->actingAs($this->customer)->post('/checkout', [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cod',
            'address' => $this->address(),
            'save_address' => true,
            'expected_total' => 200000 + 6000,
        ]);

        $order = Order::sole();
        $response->assertRedirect(route('shop.account.order', ['locale' => 'en', 'number' => $order->number]));
        $this->assertSame('01001234567', $order->customer_phone, 'spaces stripped from the phone');
        $this->assertSame(1, $variant->fresh()->stock_quantity);
        $this->assertSame(1, $this->customer->addresses()->count());

        $this->actingAs($this->customer)->get("/en/account/orders/{$order->number}")->assertOk();
        $this->actingAs($this->customer)->get('/ar/account/orders')->assertOk();
    }

    public function test_bad_phone_and_unknown_area_are_rejected(): void
    {
        $this->cart([$this->variant()->id => 1]);

        $this->actingAs($this->customer)->post('/checkout', [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cod',
            'address' => ['phone' => '12345', 'shipping_area_id' => 99999] + $this->address(),
            'expected_total' => 1,
        ])->assertSessionHasErrors(['address.phone', 'address.shipping_area_id']);

        $this->assertSame(0, Order::count());
    }

    public function test_a_changed_total_sends_the_customer_back_without_an_order(): void
    {
        $this->cart([$this->variant(price: 100000)->id => 1]);

        $this->actingAs($this->customer)->from('/en/checkout')->post('/checkout', [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cod',
            'address' => $this->address(),
            'expected_total' => 1,
        ])->assertRedirect('/en/checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_customers_see_and_cancel_only_their_own_orders(): void
    {
        $this->cart([$this->variant(stock: 5)->id => 1]);
        $order = app(PlaceOrder::class)->handle($this->customer, $this->data());
        $other = User::factory()->create();

        $this->actingAs($other)->get("/en/account/orders/{$order->number}")->assertNotFound();
        $this->actingAs($other)->post("/account/orders/{$order->number}/cancel")->assertNotFound();

        $this->actingAs($this->customer)->post("/account/orders/{$order->number}/cancel")->assertRedirect();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);

        // Once confirmed by staff, the customer can no longer cancel.
        $this->cart([$this->variant(stock: 5)->id => 1]);
        $second = app(PlaceOrder::class)->handle($this->customer, $this->data());
        $second->forceFill(['status' => OrderStatus::Confirmed])->save();
        $this->actingAs($this->customer)->post("/account/orders/{$second->number}/cancel");
        $this->assertSame(OrderStatus::Confirmed, $second->fresh()->status);
    }

    public function test_address_book_is_private_and_keeps_one_default(): void
    {
        $this->actingAs($this->customer)->post('/account/addresses', $this->address())->assertRedirect();
        $this->actingAs($this->customer)->post('/account/addresses', ['label' => 'Work'] + $this->address())->assertRedirect();
        [$first, $second] = $this->customer->addresses()->orderBy('id')->get();
        $this->assertTrue($first->is_default);
        $this->assertSame($this->area->governorate_id, $first->governorate_id);

        $this->actingAs($this->customer)->post("/account/addresses/{$second->id}/default");
        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);

        $other = User::factory()->create();
        $this->actingAs($other)->put("/account/addresses/{$first->id}", $this->address())->assertNotFound();
        $this->actingAs($other)->delete("/account/addresses/{$first->id}")->assertNotFound();

        $this->actingAs($this->customer)->delete("/account/addresses/{$second->id}");
        $this->assertSoftDeleted($second);
        $this->assertTrue($first->fresh()->is_default, 'default moves to what is left');

        $this->actingAs($this->customer)->get('/en/account/addresses')->assertOk();
        $this->assertInstanceOf(Address::class, $first);
    }

    public function test_wishlist_page_and_auth_pages_render(): void
    {
        $this->get('/en/wishlist')->assertOk();
        $this->get('/ar/wishlist')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/locale/ar')->assertRedirect();
        $this->actingAs($this->customer)->get('/settings/profile')->assertOk();
    }

    public function test_language_switch_returns_to_the_same_page(): void
    {
        $this->get('/locale/ar?return=/login')->assertRedirect('/login')->assertSessionHas('locale', 'ar');
        $this->get('/locale/en?return='.urlencode('/settings/profile?tab=1'))->assertRedirect('/settings/profile?tab=1');
        // A prefixed path opens in the language just chosen.
        $this->get('/locale/ar?return=/en/products/x')->assertRedirect('/ar/products/x');
        // No page given, or one that isn't on this site: the home page.
        $this->get('/locale/ar')->assertRedirect('/ar');
        $this->get('/locale/ar?return='.urlencode('//evil.example'))->assertRedirect('/ar');
        $this->get('/locale/ar?return='.urlencode('/\\evil.example'))->assertRedirect('/ar');
        $this->get('/locale/ar?return='.urlencode('https://evil.example'))->assertRedirect('/ar');
    }

    public function test_phone_numbers_typed_in_arabic_digits_or_with_spaces_are_accepted(): void
    {
        $this->actingAs($this->customer)
            ->post('/account/addresses', ['phone' => '٠١٠ ١٢٣٤-٥٦٧٨'] + $this->address())
            ->assertSessionHasNoErrors();

        $this->assertSame('01012345678', $this->customer->addresses()->latest('id')->value('phone'));
    }
}

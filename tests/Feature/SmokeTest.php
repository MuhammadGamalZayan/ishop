<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ShippingArea;
use App\Models\User;
use App\Services\Checkout\CheckoutData;
use App\Services\Checkout\PlaceOrder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\GovernorateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opens every admin and storefront page with the demo catalog and a real
 * order, as the right user. Catches crashes that only show with data
 * (lazy loading, missing relations, bad translations…) before a person
 * finds them. Strict models are on in tests, so lazy loading fails here.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ishop.admin_requires_2fa' => false]);
        $this->seed([RolesAndPermissionsSeeder::class, GovernorateSeeder::class, TaxRateSeeder::class, DemoCatalogSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        Coupon::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);
        $this->order = $this->placeOrder();
    }

    private function placeOrder(): Order
    {
        $variant = Product::query()->with('variants')->get()->flatMap->variants->first(fn ($v) => $v->stock_quantity > 0);
        $area = ShippingArea::query()->where('is_active', true)->firstOrFail();

        $cart = new Cart;
        $cart->forceFill(['user_id' => $this->customer->id])->save();
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        return app(PlaceOrder::class)->handle($this->customer, new CheckoutData(
            idempotencyKey: (string) Str::uuid(),
            paymentMethod: PaymentMethod::CashOnDelivery,
            address: [
                'first_name' => 'Sara', 'last_name' => 'Kamal', 'phone' => '01001234567',
                'shipping_area_id' => $area->id, 'street' => '12 Fawzy Moaz St', 'building' => '7',
            ],
        ));
    }

    public function test_every_admin_page_opens(): void
    {
        $urls = ['/admin'];

        foreach (['attributes', 'brands', 'categories', 'coupons', 'products', 'shipping', 'pages'] as $resource) {
            $urls[] = "/admin/{$resource}";
            $urls[] = "/admin/{$resource}/create";
        }

        // Every record's edit page (products and categories carry most relations).
        foreach (Product::all() as $p) {
            $urls[] = "/admin/products/{$p->slug}/edit";
        }
        foreach (Category::all() as $c) {
            $urls[] = "/admin/categories/{$c->slug}/edit";
        }
        $urls[] = '/admin/brands/'.Brand::query()->value('id').'/edit';
        $urls[] = '/admin/attributes/'.ProductAttribute::query()->value('id').'/edit';
        $urls[] = '/admin/coupons/'.Coupon::query()->value('id').'/edit';
        $urls[] = '/admin/shipping/'.Governorate::query()->whereHas('shippingAreas')->value('id').'/edit';
        $urls[] = '/admin/orders';
        $urls[] = '/admin/home-page';
        $urls[] = '/admin/contact-messages';
        $urls[] = '/admin/newsletter-subscribers';
        foreach (Page::all() as $page) {
            $urls[] = "/admin/pages/{$page->slug}/edit"; // pages are addressed by slug
        }
        $urls[] = '/admin/orders/'.$this->order->number; // orders are addressed by number

        foreach ($urls as $url) {
            $this->assertSame(200, $this->actingAs($this->admin)->get($url)->status(), $url);
        }
    }

    public function test_every_store_page_opens_for_guests_and_customers(): void
    {
        $pages = ['/en', '/ar', '/en/wishlist', '/ar/wishlist'];

        foreach (Page::all() as $page) {
            $pages[] = "/en/pages/{$page->slug}";
            $pages[] = "/ar/pages/{$page->slug}";
        }

        foreach (Category::all() as $c) {
            $pages[] = "/en/{$c->slug}";
            $pages[] = "/ar/{$c->slug}";
        }
        foreach (Product::all() as $p) {
            $pages[] = "/en/products/{$p->slug}";
            $pages[] = "/ar/products/{$p->slug}";
        }

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
            $this->actingAs($this->customer)->get($url)->assertOk();
            auth()->logout();
        }

        $this->getJson('/en/products/'.Product::query()->value('slug').'/quick')->assertOk();

        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertOk();
        }

        foreach (['/en/account/orders', '/ar/account/orders', "/en/account/orders/{$this->order->number}", '/en/account/addresses', '/settings/profile', '/settings/appearance'] as $url) {
            $this->actingAs($this->customer)->get($url)->assertOk();
        }
    }

    public function test_checkout_opens_with_a_cart(): void
    {
        $variant = Product::query()->with('variants')->get()->flatMap->variants->first(fn ($v) => $v->stock_quantity > 0);
        $cart = Cart::query()->firstOrNew(['user_id' => $this->customer->id]);
        $cart->forceFill(['user_id' => $this->customer->id])->save();
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($this->customer)->get('/en/checkout')->assertOk();
        $this->actingAs($this->customer)->get('/ar/checkout')->assertOk();
    }
}

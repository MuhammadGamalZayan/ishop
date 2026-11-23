<?php

namespace Tests\Feature\Checkout;

use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Cart;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingArea;
use App\Models\User;
use App\Services\Checkout\CheckoutData;
use App\Services\Inventory;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class CheckoutTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected ShippingArea $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxRateSeeder::class); // VAT 14%, inclusive

        $this->customer = User::factory()->create(['name' => 'Sara Kamal']);
        $governorate = Governorate::create(['name' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية']]);
        $this->area = $governorate->shippingAreas()->create(['name' => ['en' => 'Smouha', 'ar' => 'سموحة'], 'shipping_price' => 6000]);
    }

    /** A live product variant; price in piastres. */
    protected function variant(int $price = 100000, int $stock = 10): ProductVariant
    {
        $product = Product::create([
            'name' => ['en' => 'Bag '.Str::random(4), 'ar' => 'شنطة'],
            'status' => ProductStatus::Active,
            'published_at' => now()->subDay(),
        ]);

        $variant = $product->variants()->create(['sku' => 'SKU-'.Str::random(6), 'price' => $price, 'is_default' => true]);

        if ($stock > 0) {
            app(Inventory::class)->adjust($variant, $stock, StockMovementType::Restock);
        }

        return $variant->fresh();
    }

    /** Put lines straight into the customer's cart. @param array<int, int> $lines variant id => qty */
    protected function cart(array $lines, ?User $user = null): Cart
    {
        $user ??= $this->customer;
        $cart = Cart::query()->where('user_id', $user->id)->first();

        if (! $cart) {
            $cart = new Cart;
            $cart->forceFill(['user_id' => $user->id])->save();
        }

        foreach ($lines as $variantId => $quantity) {
            $cart->items()->updateOrCreate(['product_variant_id' => $variantId], ['quantity' => $quantity]);
        }

        return $cart;
    }

    protected function data(array $overrides = []): CheckoutData
    {
        return new CheckoutData(...$overrides + [
            'idempotencyKey' => (string) Str::uuid(),
            'paymentMethod' => PaymentMethod::CashOnDelivery,
            'address' => [
                'first_name' => 'Sara',
                'last_name' => 'Kamal',
                'phone' => '01001234567',
                'shipping_area_id' => $this->area->id,
                'street' => '12 Fawzy Moaz St',
                'building' => '7',
                'floor' => '3',
                'apartment' => '5',
                'landmark' => 'Near the bakery',
            ],
        ]);
    }
}

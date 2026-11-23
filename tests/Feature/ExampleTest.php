<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Storefront smoke test: the language redirect and the main pages answer. */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_sends_visitors_to_their_language(): void
    {
        $this->get('/')->assertRedirect('/en');
        $this->withHeader('Accept-Language', 'ar-EG,ar;q=0.9')->get('/')->assertRedirect('/ar');
    }

    public function test_store_pages_answer_in_both_languages(): void
    {
        $category = Category::create(['name' => ['en' => 'Bags', 'ar' => 'شنط'], 'slug' => 'bags']);
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت'], 'status' => ProductStatus::Active, 'published_at' => now()->subDay()]);
        $product->categories()->attach($category);
        $product->variants()->create(['sku' => 'TOTE', 'price' => 100000, 'is_default' => true]);

        foreach (['en', 'ar'] as $locale) {
            $this->get("/{$locale}")->assertOk();
            $this->get("/{$locale}/bags")->assertOk();
            $this->get("/{$locale}/all")->assertOk();
            $this->get("/{$locale}/products/{$product->slug}")->assertOk();
        }

        $this->get('/ar/bags')->assertSee('dir="rtl"', false);
    }

    public function test_unknown_or_hidden_pages_are_404(): void
    {
        $draft = Product::create(['name' => ['en' => 'Secret', 'ar' => 'سر'], 'status' => ProductStatus::Draft]);
        $draft->variants()->create(['sku' => 'SECRET', 'price' => 100000]);

        $this->get('/en/no-such-category')->assertNotFound();
        $this->get('/en/products/'.$draft->slug)->assertNotFound();
        $this->get('/fr')->assertNotFound();
    }

    public function test_quick_add_data_for_a_live_product_only(): void
    {
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت'], 'status' => ProductStatus::Active, 'published_at' => now()->subDay()]);
        $product->variants()->create(['sku' => 'TOTE', 'price' => 100000, 'is_default' => true]);
        $draft = Product::create(['name' => ['en' => 'Secret', 'ar' => 'سر'], 'status' => ProductStatus::Draft]);
        $draft->variants()->create(['sku' => 'SECRET', 'price' => 100000]);

        $this->getJson("/en/products/{$product->slug}/quick")
            ->assertOk()
            ->assertJsonPath('name', 'Tote')
            ->assertJsonCount(1, 'variants')
            ->assertJsonMissingPath('variants.0.stock_quantity');
        $this->getJson("/ar/products/{$product->slug}/quick")->assertJsonPath('name', 'توت');
        $this->getJson("/en/products/{$draft->slug}/quick")->assertNotFound();
    }

    public function test_button_only_addresses_redirect_instead_of_erroring(): void
    {
        $this->get('/cart/items')->assertRedirect();
        $this->get('/wishlist/1')->assertRedirect();
    }
}

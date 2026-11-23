<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'ishop_guest';

    private function variant(int $stock = 5, array $attributes = [], ProductStatus $status = ProductStatus::Active): ProductVariant
    {
        $product = Product::create([
            'name' => ['en' => 'Bag '.Str::random(4), 'ar' => 'شنطة'],
            'status' => $status,
            'published_at' => now()->subDay(),
        ]);

        $variant = $product->variants()->create($attributes + ['sku' => 'SKU-'.Str::random(6), 'price' => 100000, 'is_default' => true]);

        if ($stock > 0) {
            app(Inventory::class)->adjust($variant, $stock, StockMovementType::Restock);
        }

        return $variant->fresh();
    }

    private function add(int $variantId, int $quantity = 1, ?string $token = null)
    {
        $request = $token ? $this->withCredentials()->withCookie(self::COOKIE, $token) : $this;

        return $request->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => $quantity]);
    }

    // ---- Guests ------------------------------------------------------------

    public function test_a_guest_gets_a_secure_cookie_and_a_cart_on_first_add_only(): void
    {
        $variant = $this->variant();

        $this->get('/')->assertCookieMissing(self::COOKIE);
        $this->assertSame(0, Cart::count(), 'no cart from a page view');

        $response = $this->add($variant->id, 2)->assertOk()->assertJson(['cart_count' => 2]);

        $token = $response->getCookie(self::COOKIE)->getValue();
        $cookie = $response->getCookie(self::COOKIE, decrypt: false);
        $this->assertTrue(Str::isUuid($token));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());

        $cart = Cart::sole();
        $this->assertSame($token, $cart->token);
        $this->assertNull($cart->user_id);
        $this->assertTrue($cart->expires_at->isAfter(now()->addDays(29)));

        // Same cookie, same cart: quantities add up.
        $this->add($variant->id, 1, $token)->assertJson(['cart_count' => 3]);
        $this->assertSame(1, Cart::count());
    }

    public function test_a_forged_cookie_value_is_ignored(): void
    {
        $variant = $this->variant();

        $this->withCredentials()->withUnencryptedCookie(self::COOKIE, 'not-a-uuid')
            ->postJson('/cart/items', ['variant_id' => $variant->id])
            ->assertOk();

        $this->assertTrue(Str::isUuid(Cart::sole()->token));
    }

    public function test_stock_and_line_limits(): void
    {
        $variant = $this->variant(stock: 3);
        $token = $this->add($variant->id, 2)->getCookie(self::COOKIE)->getValue();

        $this->add($variant->id, 2, $token)
            ->assertStatus(422)
            ->assertJson(['message' => 'Only 3 left in stock, and you already have 2 in your cart.']);

        $plenty = $this->variant(stock: 100);
        $this->add($plenty->id, 11, $token)->assertStatus(422)->assertJsonValidationErrors('quantity');

        $this->add($plenty->id, 10, $token)->assertOk();
        $this->add($plenty->id, 1, $token)->assertStatus(422)->assertJson(['message' => 'You can add up to 10 of this item.']);

        $soldOut = $this->variant(stock: 0);
        $this->add($soldOut->id, 1, $token)->assertStatus(422)->assertJson(['message' => 'Sorry, this item is out of stock.']);

        $untracked = $this->variant(stock: 0, attributes: ['track_inventory' => false]);
        $this->add($untracked->id, 4, $token)->assertOk();
    }

    public function test_products_not_for_sale_cannot_be_added(): void
    {
        $draft = $this->variant(status: ProductStatus::Draft);
        $inactive = $this->variant(attributes: ['is_active' => false]);
        $deleted = $this->variant();
        $deleted->product->delete();

        foreach ([$draft->id, $inactive->id, $deleted->id, 999999] as $id) {
            $this->add($id)->assertStatus(422)->assertJson(['message' => 'This product is no longer available.']);
        }

        $this->assertSame(0, Cart::count() ? Cart::sole()->items()->count() : 0);
    }

    public function test_update_and_remove_lines(): void
    {
        $variant = $this->variant(stock: 5);
        $token = $this->add($variant->id, 1)->getCookie(self::COOKIE)->getValue();
        $line = Cart::sole()->items()->sole();

        $this->withCredentials()->withCookie(self::COOKIE, $token)->patchJson("/cart/items/{$line->id}", ['quantity' => 4])->assertJson(['cart_count' => 4]);
        $this->withCredentials()->withCookie(self::COOKIE, $token)->patchJson("/cart/items/{$line->id}", ['quantity' => 6])->assertStatus(422);
        $this->withCredentials()->withCookie(self::COOKIE, $token)->deleteJson("/cart/items/{$line->id}")->assertJson(['cart_count' => 0]);

        $this->assertSame(0, $line->cart->items()->count());
    }

    public function test_lowering_quantity_works_even_after_a_sell_out(): void
    {
        $variant = $this->variant(stock: 5);
        $token = $this->add($variant->id, 4)->getCookie(self::COOKIE)->getValue();
        app(Inventory::class)->setCount($variant, 0, note: 'Sold in shop');

        $line = Cart::sole()->items()->sole();
        $this->withCredentials()->withCookie(self::COOKIE, $token)->patchJson("/cart/items/{$line->id}", ['quantity' => 2])->assertOk();
        $this->assertSame(2, $line->fresh()->quantity);
    }

    public function test_a_shopper_cannot_touch_another_carts_lines(): void
    {
        $variant = $this->variant(stock: 5);
        $this->add($variant->id, 1);
        $victimLine = Cart::sole()->items()->sole();

        $attacker = $this->add($this->variant()->id, 1)->getCookie(self::COOKIE)->getValue();

        $this->withCredentials()->withCookie(self::COOKIE, $attacker)->patchJson("/cart/items/{$victimLine->id}", ['quantity' => 5])->assertStatus(422);
        $this->withCredentials()->withCookie(self::COOKIE, $attacker)->deleteJson("/cart/items/{$victimLine->id}")->assertStatus(422);

        $this->assertSame(1, $victimLine->fresh()->quantity);
    }

    public function test_errors_become_a_toast_for_normal_page_requests(): void
    {
        $soldOut = $this->variant(stock: 0);

        $this->from('/products/x')
            ->post('/cart/items', ['variant_id' => $soldOut->id])
            ->assertRedirect('/products/x');
    }

    public function test_cart_endpoints_are_rate_limited(): void
    {
        $variant = $this->variant(stock: 0, attributes: ['track_inventory' => false]);

        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson('/cart/items', ['variant_id' => 999999]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson('/cart/items', ['variant_id' => $variant->id])->assertStatus(429);
    }

    // ---- Signed-in users and merging ----------------------------------------

    public function test_a_signed_in_user_has_one_cart_without_any_cookie(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant();

        $this->actingAs($user)->postJson('/cart/items', ['variant_id' => $variant->id])->assertOk()->assertCookieMissing(self::COOKIE);
        $this->actingAs($user)->postJson('/cart/items', ['variant_id' => $variant->id])->assertJson(['cart_count' => 2]);

        $this->assertSame($user->id, Cart::sole()->user_id);
        $this->assertNull(Cart::sole()->token);
    }

    public function test_guest_cart_and_wishlist_merge_into_the_account_on_login(): void
    {
        $user = User::factory()->create();
        $shared = $this->variant(stock: 6);
        $onlyGuest = $this->variant(stock: 5);
        $goneLater = $this->variant(stock: 5);

        // The account already has 4 of $shared.
        $this->actingAs($user)->postJson('/cart/items', ['variant_id' => $shared->id, 'quantity' => 4]);
        $this->actingAs($user)->postJson("/wishlist/{$shared->product_id}");
        auth()->logout();

        // As a guest: 3 more of $shared, plus two other lines and a wishlist item.
        $token = $this->add($shared->id, 3)->getCookie(self::COOKIE)->getValue();
        $this->add($onlyGuest->id, 2, $token);
        $this->add($goneLater->id, 1, $token);
        $this->withCredentials()->withCookie(self::COOKIE, $token)->postJson("/wishlist/{$onlyGuest->product_id}")->assertJson(['saved' => true]);
        $goneLater->update(['is_active' => false]);

        $this->withCookie(self::COOKIE, $token)
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertCookieExpired(self::COOKIE);

        $cart = Cart::query()->where('user_id', $user->id)->sole();
        $quantities = $cart->items()->pluck('quantity', 'product_variant_id')->all();

        $this->assertSame([$shared->id => 6, $onlyGuest->id => 2], $quantities, '4 + 3 capped at stock 6; unavailable line dropped');
        $this->assertSame(1, Cart::count(), 'guest cart deleted');
        $this->assertEqualsCanonicalizing(
            [$shared->product_id, $onlyGuest->product_id],
            Wishlist::query()->where('user_id', $user->id)->sole()->items()->pluck('product_id')->all(),
        );
        $this->assertSame(1, Wishlist::count());
    }

    public function test_a_guest_cart_becomes_the_account_cart_when_there_is_none(): void
    {
        $variant = $this->variant(stock: 5);
        $token = $this->add($variant->id, 2)->getCookie(self::COOKIE)->getValue();
        $guestCartId = Cart::sole()->id;

        $this->withCredentials()->withCookie(self::COOKIE, $token)->post(route('register.store'), [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $cart = Cart::sole();
        $this->assertSame($guestCartId, $cart->id);
        $this->assertSame(User::where('email', 'new@example.com')->value('id'), $cart->user_id);
        $this->assertNull($cart->token);
        $this->assertNull($cart->expires_at);
    }

    // ---- Wishlist and pruning -------------------------------------------------

    public function test_wishlist_toggles_and_only_takes_live_products(): void
    {
        $variant = $this->variant();
        $draft = $this->variant(status: ProductStatus::Draft);

        $token = $this->postJson("/wishlist/{$variant->product_id}")->assertJson(['saved' => true])->getCookie(self::COOKIE)->getValue();
        $this->withCredentials()->withCookie(self::COOKIE, $token)->postJson("/wishlist/{$variant->product_id}")->assertJson(['saved' => false]);
        $this->withCredentials()->withCookie(self::COOKIE, $token)->postJson("/wishlist/{$draft->product_id}")->assertStatus(422);

        $this->assertSame(0, Wishlist::sole()->items()->count());
    }

    public function test_old_guest_carts_are_pruned_but_user_carts_are_kept(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant();

        $this->add($variant->id);
        Cart::query()->update(['expires_at' => now()->subDay()]);
        $this->actingAs($user)->postJson('/cart/items', ['variant_id' => $variant->id]);

        $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

        $this->assertSame([$user->id], Cart::query()->pluck('user_id')->all());
    }
}

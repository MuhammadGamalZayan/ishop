<?php

namespace App\Services\Cart;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\GuestToken;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The shopper's cart, for a signed-in user or a guest (cookie token).
 *
 * - Every change runs in a transaction holding a lock on the cart row, so
 *   two tabs adding at once can't lose an update or duplicate a line.
 * - Lines only ever reference the shopper's own cart: an item id from
 *   another cart is "not found", never touched.
 * - Stock is checked here for a friendly message only. The real guarantee
 *   is at checkout, where stock is locked and deducted (Inventory).
 * - Prices are never stored on the cart; it always shows current prices.
 */
class CartService
{
    /** Read per call, never stored: a long-lived instance must not keep an old request. */
    private function request(): Request
    {
        return app('request');
    }

    /** The current cart, without creating one. */
    public function current(): ?Cart
    {
        if ($user = $this->request()->user()) {
            return Cart::query()->where('user_id', $user->getKey())->first();
        }

        $token = GuestToken::read($this->request());

        return $token
            ? Cart::query()->where('token', $token)->where('expires_at', '>', now())->first()
            : null;
    }

    public function count(): int
    {
        return (int) $this->current()?->items()->sum('quantity');
    }

    public function add(int $variantId, int $quantity = 1): CartItem
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($variantId, $quantity): CartItem {
            $cart = $this->lockedCart(create: true);
            $variant = $this->purchasableVariant($variantId);

            $line = $cart->items()->where('product_variant_id', $variant->getKey())->first();
            $inCart = $line?->quantity ?? 0;

            if (! $line && $cart->items()->count() >= config('ishop.max_cart_lines')) {
                throw CartException::tooManyLines();
            }

            $this->assertAllowed($variant, $inCart + $quantity, $inCart);

            $line ??= $cart->items()->make(['product_variant_id' => $variant->getKey(), 'quantity' => 0]);
            $line->quantity = $inCart + $quantity;
            $line->save();

            $this->touch($cart);

            return $line;
        }, attempts: 3);
    }

    /** Set a line's quantity; 0 removes it. Returns null when removed. */
    public function setQuantity(int $itemId, int $quantity): ?CartItem
    {
        if ($quantity < 0) {
            throw CartException::lineNotFound();
        }

        return DB::transaction(function () use ($itemId, $quantity): ?CartItem {
            $cart = $this->lockedCart(create: false);
            $line = $cart?->items()->whereKey($itemId)->first() ?? throw CartException::lineNotFound();

            if ($quantity === 0) {
                $line->delete();
                $this->touch($cart);

                return null;
            }

            // Lowering the quantity is always allowed, even if the variant has since sold out.
            if ($quantity > $line->quantity) {
                $this->assertAllowed($this->purchasableVariant($line->product_variant_id), $quantity, 0);
            }

            $line->update(['quantity' => $quantity]);
            $this->touch($cart);

            return $line;
        }, attempts: 3);
    }

    public function remove(int $itemId): void
    {
        $this->setQuantity($itemId, 0);
    }

    /** Can this quantity be in a cart? Shared with the login merge. */
    public static function maxAllowed(ProductVariant $variant): int
    {
        $max = (int) config('ishop.max_line_quantity');

        return $variant->track_inventory ? min($max, $variant->stock_quantity) : $max;
    }

    private function assertAllowed(ProductVariant $variant, int $wanted, int $inCart): void
    {
        $max = (int) config('ishop.max_line_quantity');

        if ($variant->track_inventory && $variant->stock_quantity === 0) {
            throw CartException::outOfStock();
        }

        if ($variant->track_inventory && $wanted > $variant->stock_quantity) {
            throw CartException::onlyLeft($variant->stock_quantity, $inCart);
        }

        if ($wanted > $max) {
            throw CartException::maxQuantity($max);
        }
    }

    private function assertPositive(int $quantity): void
    {
        $max = (int) config('ishop.max_line_quantity');

        if ($quantity < 1 || $quantity > $max) {
            throw CartException::maxQuantity($max);
        }
    }

    /** Active variant of a product that is live on the storefront. */
    public static function purchasableVariant(int $variantId): ProductVariant
    {
        $variant = ProductVariant::query()
            ->whereKey($variantId)
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->published())
            ->first();

        return $variant ?? throw CartException::unavailable();
    }

    /** The cart row, locked for this transaction; created on first use if asked. */
    private function lockedCart(bool $create): ?Cart
    {
        $cart = $this->current();

        if (! $cart && $create) {
            $cart = $this->createCart();
        }

        return $cart ? Cart::query()->whereKey($cart->getKey())->lockForUpdate()->first() : null;
    }

    private function createCart(): Cart
    {
        $user = $this->request()->user();

        $cart = new Cart;
        $cart->forceFill($user instanceof User
            ? ['user_id' => $user->getKey()]
            : ['token' => GuestToken::readOrIssue($this->request()), 'expires_at' => $this->guestExpiry()]);

        try {
            $cart->save();
        } catch (UniqueConstraintViolationException) {
            // Another request created it a moment ago: use that one.
            return $this->current() ?? throw CartException::unavailable();
        }

        return $cart;
    }

    private function touch(Cart $cart): void
    {
        if ($cart->token !== null) {
            $cart->forceFill(['expires_at' => $this->guestExpiry()])->save();
            GuestToken::refresh($this->request(), $cart->token);
        } else {
            $cart->touch();
        }
    }

    private function guestExpiry(): CarbonInterface
    {
        return now()->addDays((int) config('ishop.guest_cart_days'));
    }
}

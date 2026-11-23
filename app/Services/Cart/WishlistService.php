<?php

namespace App\Services\Cart;

use App\Exceptions\CartException;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\GuestToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Saved products, for a signed-in user or a guest (same cookie token as
 * the cart). Holds products, not variants: the shopper picks colour and
 * size when they buy.
 */
class WishlistService
{
    /** Read per call, never stored: a long-lived instance must not keep an old request. */
    private function request(): Request
    {
        return app('request');
    }

    public function current(): ?Wishlist
    {
        if ($user = $this->request()->user()) {
            return Wishlist::query()->where('user_id', $user->getKey())->first();
        }

        $token = GuestToken::read($this->request());

        return $token
            ? Wishlist::query()->where('token', $token)->where('expires_at', '>', now())->first()
            : null;
    }

    /** @return list<int> product ids, for marking hearts on product cards */
    public function productIds(): array
    {
        return $this->current()?->items()->pluck('product_id')->all() ?? [];
    }

    /** Add if missing, remove if present. Returns true when it is now saved. */
    public function toggle(int $productId): bool
    {
        return DB::transaction(function () use ($productId): bool {
            $wishlist = $this->lockedWishlist();
            $existing = $wishlist->items()->where('product_id', $productId)->first();

            if ($existing) {
                $existing->delete();
                $this->touch($wishlist);

                return false;
            }

            if (! Product::query()->whereKey($productId)->published()->exists()) {
                throw CartException::unavailable();
            }

            if ($wishlist->items()->count() >= config('ishop.max_wishlist_items')) {
                throw CartException::wishlistFull();
            }

            $wishlist->items()->create(['product_id' => $productId]);
            $this->touch($wishlist);

            return true;
        }, attempts: 3);
    }

    private function lockedWishlist(): Wishlist
    {
        $wishlist = $this->current() ?? $this->create();

        return Wishlist::query()->whereKey($wishlist->getKey())->lockForUpdate()->firstOrFail();
    }

    private function create(): Wishlist
    {
        $user = $this->request()->user();

        $wishlist = new Wishlist;
        $wishlist->forceFill($user instanceof User
            ? ['user_id' => $user->getKey()]
            : ['token' => GuestToken::readOrIssue($this->request()), 'expires_at' => now()->addDays((int) config('ishop.guest_cart_days'))]);

        try {
            $wishlist->save();
        } catch (UniqueConstraintViolationException) {
            return $this->current() ?? throw CartException::unavailable();
        }

        return $wishlist;
    }

    private function touch(Wishlist $wishlist): void
    {
        if ($wishlist->token !== null) {
            $wishlist->forceFill(['expires_at' => now()->addDays((int) config('ishop.guest_cart_days'))])->save();
            GuestToken::refresh($this->request(), $wishlist->token);
        } else {
            $wishlist->touch();
        }
    }
}

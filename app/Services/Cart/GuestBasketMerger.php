<?php

namespace App\Services\Cart;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\GuestToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * When a guest signs in or registers, their cart and wishlist move into
 * the account in one transaction:
 * - both carts are locked (in id order, so two logins can't deadlock);
 * - quantities of the same variant are added up, then capped at the line
 *   limit and at current stock;
 * - lines that are no longer for sale are dropped;
 * - the guest rows are deleted and the guest cookie is forgotten.
 * If the account has no cart yet, the guest cart simply becomes it.
 */
class GuestBasketMerger
{
    public function merge(User $user, Request $request): void
    {
        $token = GuestToken::read($request);

        if ($token === null) {
            return;
        }

        DB::transaction(function () use ($user, $token): void {
            $this->mergeCart($user, $token);
            $this->mergeWishlist($user, $token);
        }, attempts: 3);

        GuestToken::forget($request);
    }

    private function mergeCart(User $user, string $token): void
    {
        $ids = Cart::query()
            ->where(fn ($q) => $q->where('token', $token)->orWhere('user_id', $user->getKey()))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Cart $c): string => $c->token === $token ? 'guest' : 'user');

        /** @var Cart|null $guest */
        $guest = $ids->get('guest');
        /** @var Cart|null $mine */
        $mine = $ids->get('user');

        if ($guest === null) {
            return;
        }

        if ($mine === null) {
            $guest->forceFill(['user_id' => $user->getKey(), 'token' => null, 'expires_at' => null])->save();
            $this->capLines($guest);

            return;
        }

        $lines = $mine->items()->get()->keyBy('product_variant_id');

        foreach ($guest->items()->get() as $guestLine) {
            /** @var CartItem $guestLine */
            try {
                $variant = CartService::purchasableVariant($guestLine->product_variant_id);
            } catch (CartException) {
                continue;
            }

            $existing = $lines->get($variant->getKey());
            $quantity = min(($existing?->quantity ?? 0) + $guestLine->quantity, CartService::maxAllowed($variant));

            if ($quantity < 1) {
                continue;
            }

            if ($existing) {
                $existing->update(['quantity' => $quantity]);
            } elseif ($lines->count() < config('ishop.max_cart_lines')) {
                $lines->put($variant->getKey(), $mine->items()->create(['product_variant_id' => $variant->getKey(), 'quantity' => $quantity]));
            }
        }

        if ($mine->coupon_id === null && $guest->coupon_id !== null) {
            $mine->forceFill(['coupon_id' => $guest->coupon_id]);
        }

        $mine->touch();
        $guest->delete();
    }

    /** A guest cart taken over as-is: still respect stock and limits. */
    private function capLines(Cart $cart): void
    {
        foreach ($cart->items()->get() as $line) {
            try {
                $variant = CartService::purchasableVariant($line->product_variant_id);
            } catch (CartException) {
                $line->delete();

                continue;
            }

            $max = CartService::maxAllowed($variant);

            if ($max < 1) {
                $line->delete();
            } elseif ($line->quantity > $max) {
                $line->update(['quantity' => $max]);
            }
        }
    }

    private function mergeWishlist(User $user, string $token): void
    {
        $lists = Wishlist::query()
            ->where(fn ($q) => $q->where('token', $token)->orWhere('user_id', $user->getKey()))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Wishlist $w): string => $w->token === $token ? 'guest' : 'user');

        $guest = $lists->get('guest');
        $mine = $lists->get('user');

        if ($guest === null) {
            return;
        }

        if ($mine === null) {
            $guest->forceFill(['user_id' => $user->getKey(), 'token' => null, 'expires_at' => null])->save();

            return;
        }

        $have = $mine->items()->pluck('product_id')->flip();
        $room = config('ishop.max_wishlist_items') - $have->count();

        foreach ($guest->items()->pluck('product_id') as $productId) {
            if ($room <= 0) {
                break;
            }

            if (! $have->has($productId)) {
                $mine->items()->create(['product_id' => $productId]);
                $room--;
            }
        }

        $guest->delete();
    }
}

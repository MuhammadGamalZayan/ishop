<?php

namespace App\Services\Checkout;

use App\Enums\CouponType;
use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;

/**
 * Whether a coupon may be used, and how much it takes off.
 *
 * Used twice: to preview the discount in the cart, and again inside the
 * order transaction with the coupon row locked, so the usage counts can't
 * change between the check and the redemption.
 */
class CouponRules
{
    public function find(string $code): Coupon
    {
        $coupon = Coupon::query()->where('code', mb_strtoupper(trim($code)))->first();

        return $coupon ?? throw CouponException::because('not_found');
    }

    /**
     * $lockingReads: inside the order transaction, count redemptions with a
     * locking read. Under MySQL/MariaDB REPEATABLE READ a plain SELECT sees
     * the transaction's old snapshot, so a redemption committed by another
     * order a moment ago would be missed and a single-use code used twice.
     */
    public function assertUsable(Coupon $coupon, int $subtotal, ?User $user, bool $lockingReads = false): void
    {
        $redemptions = fn () => CouponRedemption::query()
            ->where('coupon_id', $coupon->getKey())
            ->when($lockingReads, fn ($q) => $q->lockForUpdate());

        if (! $coupon->is_active || $coupon->trashed()) {
            throw CouponException::because('not_found');
        }

        if ($coupon->starts_at?->isFuture()) {
            throw CouponException::because('not_started');
        }

        if ($coupon->expires_at?->isPast()) {
            throw CouponException::because('expired');
        }

        if ($coupon->min_subtotal !== null && $subtotal < $coupon->min_subtotal) {
            throw CouponException::because('min_subtotal', ['amount' => Money::format($coupon->min_subtotal)]);
        }

        if ($coupon->usage_limit !== null && $redemptions()->count() >= $coupon->usage_limit) {
            throw CouponException::because('used_up');
        }

        if ($user !== null && $coupon->usage_limit_per_user !== null
            && $redemptions()->where('user_id', $user->getKey())->count() >= $coupon->usage_limit_per_user) {
            throw CouponException::because('already_used');
        }
    }

    /** Discount on the goods, in piastres. Free shipping returns 0 here (Pricing zeroes the shipping). */
    public function discountFor(Coupon $coupon, int $subtotal): int
    {
        $discount = match ($coupon->type) {
            CouponType::Percentage => intdiv($subtotal * $coupon->value, 10_000),
            CouponType::Fixed => $coupon->value,
            CouponType::FreeShipping => 0,
        };

        if ($coupon->max_discount !== null) {
            $discount = min($discount, $coupon->max_discount);
        }

        return max(0, min($discount, $subtotal));
    }
}

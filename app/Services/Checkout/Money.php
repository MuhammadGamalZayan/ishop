<?php

namespace App\Services\Checkout;

/** Piastres → "1,250.00 EGP" (English) / "1,250.00 ج.م" (Arabic). */
final class Money
{
    public static function format(int $piastres, ?string $locale = null): string
    {
        $amount = number_format($piastres / 100, 2);

        return ($locale ?? app()->getLocale()) === 'ar'
            ? $amount.' ج.م'
            : $amount.' '.config('ishop.currency');
    }
}

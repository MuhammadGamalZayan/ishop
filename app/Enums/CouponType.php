<?php

namespace App\Enums;

enum CouponType: string
{
    /** value = basis points (10.00% = 1000) */
    case Percentage = 'percentage';

    /** value = minor units (piastres) */
    case Fixed = 'fixed';

    case FreeShipping = 'free_shipping';
}

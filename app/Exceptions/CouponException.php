<?php

namespace App\Exceptions;

use RuntimeException;

/** A coupon code can't be used for this cart. Message is translated and safe to show. */
class CouponException extends RuntimeException
{
    public static function because(string $reason, array $replace = []): self
    {
        return new self(__("checkout.coupon.{$reason}", $replace));
    }
}

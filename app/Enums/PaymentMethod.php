<?php

namespace App\Enums;

/** How the customer chose to pay; also the payment gateway that handles it. */
enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';
    case Paymob = 'paymob';
    case Stripe = 'stripe';

    public function isOnline(): bool
    {
        return $this !== self::CashOnDelivery;
    }
}

<?php

namespace App\Enums;

/** State of a single row in `payments` (one attempt). */
enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}

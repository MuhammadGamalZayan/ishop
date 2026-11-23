<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(public readonly OrderStatus $from, public readonly OrderStatus $to)
    {
        parent::__construct(__('checkout.errors.transition', [
            'from' => __("checkout.statuses.{$from->value}"),
            'to' => __("checkout.statuses.{$to->value}"),
        ]));
    }
}

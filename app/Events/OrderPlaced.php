<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per order, only after its transaction commits, so emails and
 * other listeners never see an order that was rolled back.
 */
class OrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}

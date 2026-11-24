<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\StripePayments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Card orders not paid within ishop.payment_timeout_minutes: cancelled and
 * restocked (after stopping the payment at Stripe). Runs every minute.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel and restock online orders whose payment window has passed';

    public function handle(StripePayments $stripe): int
    {
        $expired = Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where('payment_method', PaymentMethod::Stripe->value)
            ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->where('payment_expires_at', '<', now())
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($expired as $order) {
            try {
                $stripe->expire($order);
            } catch (Throwable $e) {
                Log::error('Could not expire unpaid order', ['order' => $order->number, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Checked {$expired->count()} unpaid order(s).");

        return self::SUCCESS;
    }
}

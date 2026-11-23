<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Inventory;
use Illuminate\Support\Facades\DB;

/**
 * The only way an order's status changes (admin panel, payment webhooks,
 * the unpaid-order expiry job all call this).
 *
 * In one transaction with the order row locked:
 * - the move must be allowed by OrderStatus::allowedTransitions();
 * - a history row records who, when and why;
 * - cancelling puts the stock back and frees the coupon use;
 * - delivering a cash-on-delivery order marks it paid.
 *
 * Returned orders are NOT restocked automatically: staff inspect the items
 * and use "Adjust stock → Customer return" for what can be sold again.
 */
class OrderWorkflow
{
    public function __construct(private readonly Inventory $inventory) {}

    public function transition(Order $order, OrderStatus $to, ?User $by = null, ?string $comment = null): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $comment): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidOrderTransitionException($from, $to);
            }

            $changes = ['status' => $to];

            if ($to === OrderStatus::Cancelled) {
                $changes['cancelled_at'] = now();
                $this->restock($locked);
                CouponRedemption::query()->where('order_id', $locked->getKey())->delete();
            }

            if ($to === OrderStatus::Delivered && $locked->payment_method === PaymentMethod::CashOnDelivery) {
                $changes['payment_status'] = PaymentStatus::Paid;
            }

            $locked->forceFill($changes)->save();

            (new OrderStatusHistory)->forceFill([
                'order_id' => $locked->getKey(),
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => $by?->getKey(),
                'comment' => $comment !== null ? mb_substr($comment, 0, 255) : null,
            ])->save();

            return $locked;
        }, attempts: 3);
    }

    private function restock(Order $order): void
    {
        $quantities = $order->items()
            ->whereNotNull('product_variant_id')
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($items): int => (int) $items->sum('quantity'))
            ->all();

        if ($quantities !== []) {
            $this->inventory->release($quantities, $order, StockMovementType::Cancellation);
        }
    }
}

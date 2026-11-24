<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * Card payments through Stripe (PaymentIntents + Payment Element).
 *
 * The browser never decides that an order is paid: every status change is
 * read from Stripe's API (after the customer pays) or from a signed webhook,
 * then applied here with the order row locked, so both arriving together
 * (or twice) changes nothing the second time.
 */
class StripePayments
{
    private ?StripeClient $client = null;

    public function __construct(private readonly OrderWorkflow $workflow) {}

    public static function enabled(): bool
    {
        return filled(config('services.stripe.key')) && filled(config('services.stripe.secret'));
    }

    /** Test keys: the checkout shows Stripe's test cards. */
    public static function testMode(): bool
    {
        return str_starts_with((string) config('services.stripe.key'), 'pk_test_');
    }

    /**
     * The client secret the Payment Element confirms. The same PaymentIntent
     * is reused for retries (declined card, closed tab, double click).
     */
    public function clientSecretFor(Order $order): string
    {
        $payment = $order->payments()
            ->where('gateway', PaymentMethod::Stripe->value)
            ->where('status', PaymentAttemptStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($payment?->gateway_order_id) {
            $intent = $this->client()->paymentIntents->retrieve($payment->gateway_order_id);

            if (in_array($intent->status, [PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD, PaymentIntent::STATUS_REQUIRES_CONFIRMATION, PaymentIntent::STATUS_REQUIRES_ACTION], true)) {
                return (string) $intent->client_secret;
            }
        }

        $intent = $this->client()->paymentIntents->create([
            'amount' => $order->grand_total,             // already in piastres, Stripe's unit for EGP
            'currency' => mb_strtolower($order->currency),
            'payment_method_types' => ['card'],
            'description' => "iShop order {$order->number}",
            'receipt_email' => $order->customer_email,
            'metadata' => ['order_id' => (string) $order->getKey(), 'order_number' => $order->number],
        ], [
            // A retried request (timeout, double click) gets the same intent back.
            'idempotency_key' => "order-{$order->getKey()}-intent-".($payment?->getKey() ?? 0),
        ]);

        DB::transaction(function () use ($order, $intent): void {
            (new Payment)->forceFill([
                'order_id' => $order->getKey(),
                'gateway' => PaymentMethod::Stripe->value,
                'method' => 'card',
                'status' => PaymentAttemptStatus::Pending,
                'amount' => $intent->amount,
                'currency' => mb_strtoupper($intent->currency),
                'gateway_order_id' => $intent->id,
            ])->save();

            $order->forceFill(['payment_status' => PaymentStatus::Pending])->save();
        });

        return (string) $intent->client_secret;
    }

    /** Re-read an order's latest intent from Stripe and apply it. */
    public function refresh(Order $order): Order
    {
        $payment = $order->payments()->where('gateway', PaymentMethod::Stripe->value)->latest('id')->first();

        if ($payment?->gateway_order_id) {
            $this->apply($this->client()->paymentIntents->retrieve($payment->gateway_order_id));
        }

        return $order->refresh();
    }

    /**
     * Apply a PaymentIntent's state to its payment and order (idempotent).
     * Amount and currency must match the order, or nothing is marked paid.
     */
    public function apply(PaymentIntent $intent): void
    {
        DB::transaction(function () use ($intent): void {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('gateway', PaymentMethod::Stripe->value)
                ->where('gateway_order_id', $intent->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('Stripe intent with no matching payment', ['intent' => $intent->id]);

                return;
            }

            /** @var Order $order */
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentAttemptStatus::Paid) {
                return; // already applied
            }

            if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
                if ($intent->amount_received !== $order->grand_total || mb_strtoupper($intent->currency) !== $order->currency) {
                    Log::error('Stripe amount mismatch', ['order' => $order->number, 'intent' => $intent->id, 'received' => $intent->amount_received]);

                    return;
                }

                $payment->forceFill([
                    'status' => PaymentAttemptStatus::Paid,
                    'gateway_transaction_id' => is_string($intent->latest_charge) ? $intent->latest_charge : $intent->latest_charge?->id,
                    'paid_at' => now(),
                    'payload' => ['status' => $intent->status, 'amount_received' => $intent->amount_received],
                ])->save();

                $order->forceFill(['payment_status' => PaymentStatus::Paid, 'payment_expires_at' => null])->save();

                if ($order->status === OrderStatus::Cancelled) {
                    // Paid after the order expired: money taken for an order we no longer hold.
                    Log::critical('Stripe payment succeeded for a cancelled order: refund it in the Stripe dashboard', ['order' => $order->number]);
                }

                return;
            }

            if ($intent->status === PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD && $intent->last_payment_error) {
                // Declined: the customer may try another card until the order expires.
                $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();
            }

            if ($intent->status === PaymentIntent::STATUS_CANCELED) {
                $payment->forceFill(['status' => PaymentAttemptStatus::Failed, 'failed_at' => now()])->save();
            }
        });
    }

    /**
     * An online order not paid in time: stop the intent at Stripe (so it can
     * no longer be paid), then cancel the order, which restocks it. If Stripe
     * says it was paid after all, it is marked paid instead.
     */
    public function expire(Order $order): void
    {
        $payments = $order->payments()->where('gateway', PaymentMethod::Stripe->value)->where('status', PaymentAttemptStatus::Pending->value)->get();

        foreach ($payments as $payment) {
            try {
                $intent = $this->client()->paymentIntents->retrieve((string) $payment->gateway_order_id);

                if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
                    $this->apply($intent);

                    return;
                }

                if ($intent->status !== PaymentIntent::STATUS_CANCELED) {
                    $this->apply($this->client()->paymentIntents->cancel($intent->id));
                } else {
                    $this->apply($intent);
                }
            } catch (ApiErrorException $e) {
                // Leave it for the next run rather than cancel an order that might be paying right now.
                Log::warning('Could not expire Stripe payment', ['order' => $order->number, 'error' => $e->getMessage()]);

                return;
            }
        }

        $this->workflow->transition($order, OrderStatus::Cancelled, comment: 'Payment not completed in time');
        Order::query()->whereKey($order->getKey())->update(['payment_status' => PaymentStatus::Failed->value]);
    }

    public function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('services.stripe.secret'));
    }

    /** Tests swap in a fake client. */
    public function useClient(StripeClient $client): void
    {
        $this->client = $client;
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Services\Payments\StripePayments;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * Stripe → POST /webhooks/stripe (no CSRF; authenticated by signature).
 *
 * Only signed events are acted on. Each event id is stored once
 * (payment_webhook_events is unique on it), so Stripe's retries and
 * duplicate deliveries are answered 200 without being applied twice.
 * The intent is re-read from Stripe's API rather than trusted from the body.
 */
class StripeWebhookController extends Controller
{
    private const HANDLED = ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.canceled'];

    public function __invoke(Request $request, StripePayments $stripe): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            return response('Webhook secret not configured', 503);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid signature', 400);
        }

        try {
            $record = DB::table('payment_webhook_events')->insertGetId([
                'gateway' => PaymentMethod::Stripe->value,
                'event_key' => $event->id,
                'signature_valid' => true,
                'payload' => json_encode(['type' => $event->type, 'object' => $event->data->object->id ?? null]),
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('Already received', 200);
        }

        if (in_array($event->type, self::HANDLED, true) && $event->data->object instanceof PaymentIntent) {
            try {
                $stripe->apply($stripe->client()->paymentIntents->retrieve($event->data->object->id));
            } catch (Throwable $e) {
                DB::table('payment_webhook_events')->where('id', $record)->update(['error' => mb_substr($e->getMessage(), 0, 2000)]);
                Log::error('Stripe webhook failed', ['event' => $event->id, 'error' => $e->getMessage()]);

                // Let Stripe retry: forget this delivery so the retry is processed.
                DB::table('payment_webhook_events')->where('id', $record)->delete();

                return response('Retry later', 500);
            }
        }

        DB::table('payment_webhook_events')->where('id', $record)->update(['processed_at' => now()]);

        return response('OK', 200);
    }
}

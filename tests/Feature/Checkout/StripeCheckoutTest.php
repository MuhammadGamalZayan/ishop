<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripe;

class StripeCheckoutTest extends CheckoutTestCase
{
    private FakeStripe $stripe;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe' => ['key' => 'pk_test_123', 'secret' => 'sk_test_123', 'webhook_secret' => 'whsec_test']]);
        ApiRequestor::setHttpClient($this->stripe = new FakeStripe);

        $this->variant = $this->variant(price: 100000, stock: 5);
        $this->cart([$this->variant->id => 2]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    private function payByCard(?string $key = null, ?int $expected = null): TestResponse
    {
        return $this->actingAs($this->customer)->postJson('/checkout', [
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'payment_method' => 'stripe',
            'address_id' => null,
            'address' => $this->data()->address,
            'save_address' => false,
            'expected_total' => $expected ?? 206000, // 2 x 1,000 + 60 shipping
        ]);
    }

    public function test_a_card_order_holds_the_stock_and_returns_a_client_secret(): void
    {
        $this->payByCard()->assertOk()->assertJsonStructure(['client_secret', 'return_url']);

        $order = Order::query()->sole();
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertTrue($order->payment_expires_at->between(now()->addMinutes(29), now()->addMinutes(31)));
        $this->assertSame(3, $this->variant->fresh()->stock_quantity);

        $payment = Payment::query()->sole();
        $this->assertSame(206000, $payment->amount);
        $this->assertSame('EGP', $payment->currency);
        $this->assertSame('egp', $this->stripe->intents[$payment->gateway_order_id]['currency']);
    }

    public function test_a_retry_after_a_declined_card_reuses_the_order_and_the_intent(): void
    {
        $key = (string) Str::uuid();
        $first = $this->payByCard($key)->json('client_secret');
        $this->stripe->decline($this->stripe->latestId());

        $second = $this->payByCard($key)->json('client_secret');

        $this->assertSame($first, $second);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_returning_after_payment_marks_the_order_paid_from_stripe_not_the_browser(): void
    {
        $this->payByCard();
        $order = Order::query()->sole();

        // Not paid yet: the query string can't make it paid.
        $this->actingAs($this->customer)->get("/checkout/stripe/return?order={$order->number}&redirect_status=succeeded")
            ->assertRedirect(route('shop.account.order', ['number' => $order->number]));
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);

        $this->stripe->succeed($this->stripe->latestId());
        $this->actingAs($this->customer)->get("/checkout/stripe/return?order={$order->number}");

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNull($order->payment_expires_at);
        $this->assertSame(PaymentAttemptStatus::Paid, Payment::query()->sole()->status);
    }

    public function test_a_different_amount_is_never_marked_paid(): void
    {
        $this->payByCard();
        $order = Order::query()->sole();
        $this->stripe->succeed($this->stripe->latestId(), amountReceived: 100);

        $this->actingAs($this->customer)->get("/checkout/stripe/return?order={$order->number}");

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_customers_cannot_open_someone_elses_return_page(): void
    {
        $this->payByCard();
        $order = Order::query()->sole();
        $other = User::factory()->create();

        $this->actingAs($other)->get("/checkout/stripe/return?order={$order->number}")->assertNotFound();
    }

    public function test_the_webhook_needs_a_valid_signature_and_applies_each_event_once(): void
    {
        $this->payByCard();
        $order = Order::query()->sole();
        $intent = $this->stripe->latestId();
        $this->stripe->succeed($intent);

        $payload = json_encode([
            'id' => 'evt_1',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $intent, 'object' => 'payment_intent']],
        ]);

        $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=forged', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);

        $signed = fn (): TestResponse => $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1='.hash_hmac('sha256', time().'.'.$payload, 'whsec_test'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $signed()->assertOk();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $signed()->assertOk()->assertSee('Already received');
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_unpaid_card_orders_expire_and_restock(): void
    {
        $this->payByCard();
        $order = Order::query()->sole();
        $this->travel(31)->minutes();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(5, $this->variant->fresh()->stock_quantity);
        $this->assertSame('canceled', $this->stripe->intents[$this->stripe->latestId()]['status']);
    }

    public function test_an_order_paid_at_the_last_moment_is_not_expired(): void
    {
        $this->payByCard();
        $order = Order::query()->sole();
        $this->stripe->succeed($this->stripe->latestId()); // webhook not arrived yet
        $this->travel(31)->minutes();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(3, $this->variant->fresh()->stock_quantity);
    }

    public function test_card_payment_is_refused_while_stripe_is_not_configured(): void
    {
        config(['services.stripe.secret' => null]);

        $this->payByCard()->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_the_checkout_page_gets_only_the_publishable_key(): void
    {
        $this->actingAs($this->customer)->get('/en/checkout')
            ->assertInertia(fn ($page) => $page
                ->where('stripe.key', 'pk_test_123')
                ->where('stripe.test', true)
                ->where('stripe.currency', 'egp')
                ->missing('stripe.secret'));
    }
}

<?php

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Stands in for Stripe's HTTP API in tests: keeps PaymentIntents in memory
 * and answers create / retrieve / cancel like the real API.
 * Install with Stripe\ApiRequestor::setHttpClient(new FakeStripe).
 */
class FakeStripe implements ClientInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $intents = [];

    /** @var list<string> idempotency keys seen on create */
    public array $idempotencyKeys = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);

        if ($method === 'post' && $path === '/v1/payment_intents') {
            foreach ($headers as $header) {
                if (str_starts_with(strtolower($header), 'idempotency-key:')) {
                    $key = trim(substr($header, strlen('idempotency-key:')));

                    // Same key = Stripe replays the first response.
                    foreach ($this->intents as $intent) {
                        if (($intent['_key'] ?? null) === $key) {
                            return $this->json($intent);
                        }
                    }

                    $this->idempotencyKeys[] = $key;
                }
            }

            $id = 'pi_test_'.(count($this->intents) + 1);
            $this->intents[$id] = [
                'id' => $id,
                'object' => 'payment_intent',
                'amount' => (int) $params['amount'],
                'amount_received' => 0,
                'currency' => $params['currency'],
                'status' => 'requires_payment_method',
                'client_secret' => $id.'_secret_abc',
                'latest_charge' => null,
                'last_payment_error' => null,
                'metadata' => $params['metadata'] ?? [],
                '_key' => $key ?? null,
            ];

            return $this->json($this->intents[$id]);
        }

        if (preg_match('#^/v1/payment_intents/([^/]+)(/cancel)?$#', $path, $m)) {
            $intent = $this->intents[$m[1]] ?? null;

            if ($intent === null) {
                return [json_encode(['error' => ['type' => 'invalid_request_error', 'message' => 'No such payment_intent']]), 404, []];
            }

            if (($m[2] ?? '') === '/cancel') {
                $this->intents[$m[1]]['status'] = 'canceled';
            }

            return $this->json($this->intents[$m[1]]);
        }

        return [json_encode(['error' => ['type' => 'invalid_request_error', 'message' => "Unexpected {$method} {$path}"]]), 400, []];
    }

    /** The customer paid (as after confirmPayment with a good card). */
    public function succeed(string $id, ?int $amountReceived = null): void
    {
        $this->intents[$id]['status'] = 'succeeded';
        $this->intents[$id]['amount_received'] = $amountReceived ?? $this->intents[$id]['amount'];
        $this->intents[$id]['latest_charge'] = 'ch_'.$id;
    }

    public function decline(string $id): void
    {
        $this->intents[$id]['status'] = 'requires_payment_method';
        $this->intents[$id]['last_payment_error'] = ['code' => 'card_declined', 'message' => 'Your card was declined.'];
    }

    public function latestId(): string
    {
        return (string) array_key_last($this->intents);
    }

    /** @param array<string, mixed> $body */
    private function json(array $body): array
    {
        unset($body['_key']);

        return [json_encode($body), 200, []];
    }
}

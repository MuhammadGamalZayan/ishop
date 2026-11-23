<?php

/**
 * Child process for the concurrency tests: places one order and prints the
 * outcome as JSON. Usage:
 *   php place-order.php <userId> <idempotencyKey> <startAtMicrotime> [couponCode]
 * Runs against the database given in the environment (ishop_testing).
 */

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Models\User;
use App\Services\Checkout\CheckoutData;
use App\Services\Checkout\PlaceOrder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $userId, $key, $startAt] = $argv;
$coupon = $argv[4] ?? null;

$user = User::findOrFail((int) $userId);
$areaId = (int) getenv('ISHOP_TEST_AREA');

// Everyone waits for the same instant, then goes.
while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $order = app(PlaceOrder::class)->handle($user, new CheckoutData(
        idempotencyKey: $key,
        paymentMethod: PaymentMethod::CashOnDelivery,
        address: [
            'first_name' => 'Race', 'last_name' => 'Test', 'phone' => '01000000000',
            'shipping_area_id' => $areaId, 'street' => 'S', 'building' => '1',
        ],
        couponCode: $coupon,
    ));

    echo json_encode(['ok' => true, 'order' => $order->id]);
} catch (CheckoutException|CouponException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'crash' => get_class($e).': '.$e->getMessage()]);
}

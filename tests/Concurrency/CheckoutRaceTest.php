<?php

namespace Tests\Concurrency;

use App\Enums\CouponType;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

/**
 * Real races: several PHP processes place orders at the same instant
 * against MySQL/MariaDB, where row locks actually apply (SQLite has none).
 *
 * Uses its own database `ishop_testing` (created if missing) and skips if
 * the server isn't reachable. Run: php artisan test tests/Concurrency
 * Before go-live, run it against MariaDB too (production engine).
 */
class CheckoutRaceTest extends TestCase
{
    private const DB = 'ishop_testing';

    private int $areaId;

    protected function setUp(): void
    {
        parent::setUp();

        $host = env('ISHOP_RACE_DB_HOST', '127.0.0.1');

        try {
            (new PDO("mysql:host={$host};port=3306", 'root', ''))
                ->exec('CREATE DATABASE IF NOT EXISTS '.self::DB.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL/MariaDB not reachable: '.$e->getMessage());
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $host,
            'database.connections.mysql.database' => self::DB,
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
        DB::purge('mysql');

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed(TaxRateSeeder::class);

        $governorate = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $this->areaId = $governorate->shippingAreas()->create(['name' => ['en' => 'A', 'ar' => 'أ'], 'shipping_price' => 5000])->id;
    }

    private function variant(int $stock): ProductVariant
    {
        $product = Product::create(['name' => ['en' => 'Last bag', 'ar' => 'آخر شنطة'], 'status' => ProductStatus::Active, 'published_at' => now()->subDay()]);
        $variant = $product->variants()->create(['sku' => 'RACE-'.Str::random(5), 'price' => 100000, 'is_default' => true]);
        app(Inventory::class)->adjust($variant, $stock, StockMovementType::Restock);

        return $variant;
    }

    private function shopper(ProductVariant $variant, int $quantity = 1): User
    {
        $user = User::factory()->create();
        $cart = new Cart;
        $cart->forceFill(['user_id' => $user->id])->save();
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);

        return $user;
    }

    /**
     * Start all runs, release them at the same instant, collect results.
     *
     * @param  list<array{0: int, 1: string, 2?: string}>  $runs  [userId, key, coupon?]
     * @return list<array<string, mixed>>
     */
    private function race(array $runs): array
    {
        $startAt = microtime(true) + 2.0; // time for every process to boot first
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_DATABASE' => self::DB,
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'ISHOP_TEST_AREA' => (string) $this->areaId,
        ];

        $processes = array_map(function (array $run) use ($startAt, $env): Process {
            $process = new Process([PHP_BINARY, __DIR__.'/place-order.php', (string) $run[0], $run[1], (string) $startAt, $run[2] ?? ''], base_path(), $env, null, 60);
            $process->start();

            return $process;
        }, $runs);

        return array_map(function (Process $p): array {
            $p->wait();
            $result = json_decode($p->getOutput(), true);
            $this->assertIsArray($result, 'child output: '.$p->getOutput().$p->getErrorOutput());
            $this->assertArrayNotHasKey('crash', $result, $result['crash'] ?? '');

            return $result;
        }, $processes);
    }

    public function test_five_customers_one_last_unit(): void
    {
        $variant = $this->variant(stock: 1);
        $runs = array_map(fn (): array => [$this->shopper($variant)->id, (string) Str::uuid()], range(1, 5));

        $results = $this->race($runs);

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), 'exactly one order wins: '.json_encode($results));
        $this->assertSame(1, Order::count());
        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::Sale)->count());
    }

    public function test_crossed_carts_do_not_deadlock(): void
    {
        // Two shoppers with the same two variants; locks in id order means no deadlock.
        $a = $this->variant(stock: 10);
        $b = $this->variant(stock: 10);
        $runs = [];
        foreach (range(1, 4) as $i) {
            $user = $this->shopper($a, 1);
            Cart::query()->where('user_id', $user->id)->first()->items()->create(['product_variant_id' => $b->id, 'quantity' => 1]);
            $runs[] = [$user->id, (string) Str::uuid()];
        }

        $results = $this->race($runs);

        $this->assertCount(4, array_filter($results, fn ($r) => $r['ok']), json_encode($results));
        $this->assertSame([6, 6], [$a->fresh()->stock_quantity, $b->fresh()->stock_quantity]);
    }

    public function test_a_single_use_coupon_is_redeemed_once(): void
    {
        Coupon::create(['code' => 'ONLYONE', 'type' => CouponType::Fixed, 'value' => 1000, 'usage_limit' => 1]);
        $variant = $this->variant(stock: 50);
        $runs = array_map(fn (): array => [$this->shopper($variant)->id, (string) Str::uuid(), 'ONLYONE'], range(1, 5));

        $results = $this->race($runs);

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), json_encode($results));
        $this->assertSame(1, CouponRedemption::count());
    }

    public function test_a_double_submit_makes_one_order(): void
    {
        $variant = $this->variant(stock: 5);
        $user = $this->shopper($variant);
        $key = (string) Str::uuid();

        $results = $this->race([[$user->id, $key], [$user->id, $key], [$user->id, $key]]);

        $this->assertTrue(collect($results)->every(fn ($r) => $r['ok']), json_encode($results));
        $this->assertCount(1, array_unique(array_column($results, 'order')), 'all three got the same order');
        $this->assertSame(1, Order::count());
        $this->assertSame(4, $variant->fresh()->stock_quantity);
    }
}

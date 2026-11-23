<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Logic of the stock ledger. Real row-lock races need MariaDB and are
 * covered by the concurrency tests that come with checkout.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Inventory $inventory;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventory = app(Inventory::class);
        $this->product = Product::create(['name' => ['en' => 'Bag', 'ar' => 'شنطة']]);
    }

    private function variant(int $stock = 0, bool $tracked = true): ProductVariant
    {
        $variant = $this->product->variants()->create([
            'sku' => 'SKU-'.uniqid(),
            'price' => 100000,
            'track_inventory' => $tracked,
        ]);

        if ($stock > 0) {
            $this->inventory->adjust($variant, $stock, StockMovementType::Restock);
        }

        return $variant->fresh();
    }

    public function test_stock_cannot_be_set_through_mass_assignment(): void
    {
        $variant = $this->variant();

        try {
            // Strict mode (outside production) turns the silent drop into an error.
            $variant->fill(['stock_quantity' => 99])->save();
        } catch (MassAssignmentException) {
            // expected
        }

        $this->assertSame(0, $variant->fresh()->stock_quantity);
    }

    public function test_restock_adds_stock_and_writes_one_ledger_row(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant();

        $movement = $this->inventory->adjust($variant, 10, StockMovementType::Restock, $user, 'Supplier delivery');

        $this->assertSame(10, $variant->fresh()->stock_quantity);
        $this->assertSame(10, $movement->quantity);
        $this->assertSame(10, $movement->stock_after);
        $this->assertSame($user->id, $movement->user_id);
        $this->assertSame('Supplier delivery', $movement->note);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_stock_never_goes_below_zero(): void
    {
        $variant = $this->variant(3);

        try {
            $this->inventory->adjust($variant, -5, StockMovementType::Adjustment);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame(3, $e->available);
            $this->assertSame(5, $e->requested);
        }

        $this->assertSame(3, $variant->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::count(), 'only the initial restock');
    }

    public function test_stock_count_records_the_difference(): void
    {
        $variant = $this->variant(10);

        $movement = $this->inventory->setCount($variant, 7, note: 'Monthly count');

        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(7, $variant->fresh()->stock_quantity);
        $this->assertSame(StockMovementType::Adjustment, $movement->type);
        $this->assertNull($this->inventory->setCount($variant, 7), 'same count writes nothing');
    }

    public function test_an_order_deducts_all_lines_or_none(): void
    {
        $a = $this->variant(5);
        $b = $this->variant(1);
        $order = Product::create(['name' => ['en' => 'Reference', 'ar' => 'مرجع']]); // any model works as a reference here

        try {
            $this->inventory->deduct([$a->id => 2, $b->id => 2], $order);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame($b->id, $e->variantId);
        }

        $this->assertSame(5, $a->fresh()->stock_quantity, 'first line rolled back');
        $this->assertSame(1, $b->fresh()->stock_quantity);

        $movements = $this->inventory->deduct([$b->id => 1, $a->id => 2], $order);

        $this->assertSame([$a->id, $b->id], array_map(fn ($m) => $m->product_variant_id, $movements), 'locked and written in id order');
        $this->assertSame(3, $a->fresh()->stock_quantity);
        $this->assertSame(0, $b->fresh()->stock_quantity);
        $this->assertSame($order->getMorphClass(), $movements[0]->reference_type);
        $this->assertSame(StockMovementType::Sale, $movements[0]->type);
    }

    public function test_cancellation_puts_stock_back(): void
    {
        $variant = $this->variant(2);
        $order = Product::create(['name' => ['en' => 'Reference', 'ar' => 'مرجع']]);

        $this->inventory->deduct([$variant->id => 2], $order);
        $this->inventory->release([$variant->id => 2], $order);

        $this->assertSame(2, $variant->fresh()->stock_quantity);
        $this->assertSame([2, -2, 2], StockMovement::orderBy('id')->pluck('quantity')->all());
    }

    public function test_untracked_variants_are_not_counted_on_sales(): void
    {
        $variant = $this->variant(tracked: false);
        $order = Product::create(['name' => ['en' => 'Reference', 'ar' => 'مرجع']]);

        $this->assertSame([], $this->inventory->deduct([$variant->id => 4], $order));
        $this->assertSame(0, $variant->fresh()->stock_quantity);
    }

    public function test_bad_quantities_are_rejected(): void
    {
        $variant = $this->variant(5);
        $order = Product::create(['name' => ['en' => 'Reference', 'ar' => 'مرجع']]);

        foreach ([0, -1] as $quantity) {
            try {
                $this->inventory->deduct([$variant->id => $quantity], $order);
                $this->fail("Quantity {$quantity} should be rejected");
            } catch (InvalidArgumentException) {
                // expected
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->inventory->release([$variant->id => 1], $order, StockMovementType::Sale);
    }
}

<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place where product_variants.stock_quantity changes.
 *
 * Every change, in one transaction:
 * 1. locks the variant rows (SELECT … FOR UPDATE), always in ascending id
 *    order so two orders touching the same variants cannot deadlock;
 * 2. checks the new quantity never goes below zero (the DB CHECK constraint
 *    is the last line of defence behind this);
 * 3. writes the new quantity and one stock_movements row per variant.
 *
 * Called inside an outer transaction (checkout) it joins it, so the stock
 * change commits or rolls back together with the order.
 */
class Inventory
{
    /** Movements that only matter for variants whose stock is tracked. */
    private const TRACKED_ONLY = [StockMovementType::Sale, StockMovementType::Cancellation, StockMovementType::Return];

    /**
     * Add or remove stock on one variant (admin restock, damage, return…).
     * $delta is signed: +10 restock, -2 damaged.
     */
    public function adjust(ProductVariant $variant, int $delta, StockMovementType $type, ?User $user = null, ?string $note = null, ?Model $reference = null): ?StockMovement
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('A stock adjustment needs a non-zero quantity.');
        }

        return $this->apply([$variant->getKey() => $delta], $type, $user, $note, $reference)[0] ?? null;
    }

    /**
     * Stock count: set the real number found on the shelf. The difference is
     * worked out under the lock, so a sale made a moment earlier is not lost.
     * Returns null when the count matches and nothing changed.
     */
    public function setCount(ProductVariant $variant, int $counted, ?User $user = null, ?string $note = null): ?StockMovement
    {
        if ($counted < 0) {
            throw new InvalidArgumentException('A stock count cannot be negative.');
        }

        return DB::transaction(function () use ($variant, $counted, $user, $note): ?StockMovement {
            $current = ProductVariant::query()->whereKey($variant->getKey())->lockForUpdate()->value('stock_quantity');
            $delta = $counted - (int) $current;

            return $delta === 0
                ? null
                : $this->apply([$variant->getKey() => $delta], StockMovementType::Adjustment, $user, $note)[0] ?? null;
        }, attempts: 3);
    }

    /**
     * Take stock for an order: [variant id => quantity]. All or nothing:
     * if any line is short, nothing is taken and InsufficientStockException
     * names the first short variant.
     *
     * @param  array<int, int>  $quantities
     * @return list<StockMovement>
     */
    public function deduct(array $quantities, Model $reference): array
    {
        return $this->apply($this->signed($quantities, -1), StockMovementType::Sale, null, null, $reference);
    }

    /**
     * Put stock back for a cancelled or returned order.
     *
     * @param  array<int, int>  $quantities
     * @return list<StockMovement>
     */
    public function release(array $quantities, Model $reference, StockMovementType $type = StockMovementType::Cancellation): array
    {
        if (! in_array($type, [StockMovementType::Cancellation, StockMovementType::Return], true)) {
            throw new InvalidArgumentException('Stock can only be released by a cancellation or a return.');
        }

        return $this->apply($this->signed($quantities, 1), $type, null, null, $reference);
    }

    /**
     * @param  array<int, int>  $quantities
     * @return array<int, int>
     */
    private function signed(array $quantities, int $sign): array
    {
        $deltas = [];

        foreach ($quantities as $variantId => $quantity) {
            if (! is_int($quantity) || $quantity < 1) {
                throw new InvalidArgumentException("Quantity for variant {$variantId} must be a positive integer.");
            }

            $deltas[(int) $variantId] = $sign * $quantity;
        }

        return $deltas;
    }

    /**
     * @param  array<int, int>  $deltas  variant id => signed change
     * @return list<StockMovement>
     */
    private function apply(array $deltas, StockMovementType $type, ?User $user, ?string $note, ?Model $reference = null): array
    {
        if ($deltas === []) {
            return [];
        }

        ksort($deltas);

        return DB::transaction(function () use ($deltas, $type, $user, $note, $reference): array {
            // withTrashed: a cancelled order must still restock a variant that was deleted since.
            $variants = ProductVariant::withTrashed()
                ->whereKey(array_keys($deltas))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $movements = [];

            foreach ($deltas as $variantId => $delta) {
                /** @var ProductVariant|null $variant */
                $variant = $variants->get($variantId);

                if ($variant === null) {
                    throw new InvalidArgumentException("Variant {$variantId} does not exist.");
                }

                if (! $variant->track_inventory && in_array($type, self::TRACKED_ONLY, true)) {
                    continue;
                }

                $after = $variant->stock_quantity + $delta;

                if ($after < 0) {
                    throw new InsufficientStockException($variant->getKey(), $variant->sku, $variant->stock_quantity, -$delta);
                }

                // stock_quantity is not fillable on purpose; this is the one writer.
                ProductVariant::query()->whereKey($variantId)->update(['stock_quantity' => $after]);
                $variant->setRawAttributes(['stock_quantity' => $after] + $variant->getAttributes(), sync: true);

                $movements[] = StockMovement::create([
                    'product_variant_id' => $variantId,
                    'type' => $type,
                    'quantity' => $delta,
                    'stock_after' => $after,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'user_id' => $user?->getKey(),
                    'note' => $note,
                ]);
            }

            return $movements;
        }, attempts: 3);
    }
}

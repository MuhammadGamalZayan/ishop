<?php

namespace App\Models;

use App\Models\Concerns\HasProductImages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;

/**
 * The sellable unit: SKU, price and stock. Every product has at least one.
 *
 * stock_quantity is NOT fillable on purpose: it may only change through
 * App\Services\Inventory (row lock + stock_movements ledger in one transaction).
 *
 * @property int $price piastres
 * @property int|null $compare_at_price piastres
 * @property int $stock_quantity
 */
#[Fillable([
    'product_id', 'sku', 'barcode', 'price', 'compare_at_price', 'cost_price',
    'weight_grams', 'is_default', 'is_active', 'track_inventory', 'low_stock_threshold',
])]
class ProductVariant extends Model implements HasMedia
{
    use HasProductImages, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        // Keep the product's search text in sync with its SKUs.
        static::saved(function (ProductVariant $variant): void {
            if ($variant->wasChanged('sku') || $variant->wasRecentlyCreated) {
                $product = $variant->product;
                $product->search_text = $product->buildSearchText();
                $product->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost_price' => 'integer',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsToMany<ProductAttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'attribute_value_product_variant', 'product_variant_id', 'attribute_value_id');
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isInStock(int $quantity = 1): bool
    {
        return ! $this->track_inventory || $this->stock_quantity >= $quantity;
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Price changes are audited; stock is audited by stock_movements instead.
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}

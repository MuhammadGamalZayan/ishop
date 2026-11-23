<?php

namespace App\Support\Storefront;

use App\Enums\AttributeType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shapes catalog data for the storefront pages (plain arrays for Inertia).
 * Only published products, active variants and active categories appear.
 */
class Catalog
{
    /** @var array<int, true>|null */
    private ?array $bestSellers = null;

    /** Top-level categories with their sub-categories, for the header and mega menu. */
    public function navigation(): array
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $c): array => [
                'name' => $c->name,
                'url' => route('shop.category', ['slug' => $c->slug]),
                'children' => $c->children->map(fn (Category $child): array => [
                    'name' => $child->name,
                    'url' => route('shop.category', ['slug' => $child->slug]),
                ])->values()->all(),
            ])
            ->all();
    }

    /** Products ready for cards: eager loads everything a card needs. */
    public function cardQuery(): Builder
    {
        return Product::query()
            ->published()
            ->whereHas('variants', fn ($q) => $q->where('is_active', true))
            ->with([
                'media',
                'variants' => fn ($q) => $q->where('is_active', true)->with('attributeValues.productAttribute'),
            ]);
    }

    /** @param  Collection<int, Product>  $products */
    public function cards(Collection $products): array
    {
        return $products->map(fn (Product $p): array => $this->card($p))->values()->all();
    }

    public function card(Product $product): array
    {
        /** @var Collection<int, ProductVariant> $variants */
        $variants = $product->variants;
        $default = $variants->firstWhere('is_default', true) ?? $variants->first();
        $min = (int) $variants->min('price');
        $max = (int) $variants->max('price');
        $colours = $this->colours($variants);
        $inStock = $variants->contains(fn (ProductVariant $v): bool => $v->isInStock());
        $onSale = $default?->isOnSale() ?? false;

        return [
            'id' => $product->getKey(),
            'name' => $product->name,
            'url' => route('shop.product', ['slug' => $product->slug]),
            'price' => $min,
            'compare_at' => $onSale ? $default->compare_at_price : null,
            'price_varies' => $min !== $max,
            'image' => $product->getFirstMediaUrl('images', 'card') ?: null,
            'image_hover' => $product->getMedia('images')->get(1)?->getUrl('card'),
            'swatch' => $colours[0]['hex'] ?? null,
            'colours' => count($colours),
            'badge' => ! $inStock ? 'sold_out' : ($this->isBestSeller($product) ? 'best' : ($onSale ? 'sale' : ($this->isNew($product) ? 'new' : null))),
            'in_stock' => $inStock,
            // One variant and nothing to choose: the + button adds it straight away.
            'quick_variant_id' => $variants->count() === 1 && $inStock ? $default?->getKey() : null,
        ];
    }

    /** @param  Collection<int, ProductVariant>  $variants */
    private function colours(Collection $variants): array
    {
        return $variants
            ->flatMap(fn (ProductVariant $v) => $v->attributeValues)
            ->filter(fn (ProductAttributeValue $value): bool => $value->productAttribute->type === AttributeType::Color)
            ->unique('id')
            ->map(fn (ProductAttributeValue $value): array => ['name' => $value->value, 'hex' => $value->color_hex])
            ->values()
            ->all();
    }

    private function isNew(Product $product): bool
    {
        return $product->published_at !== null && $product->published_at->gt(now()->subDays(30));
    }

    /** Top 10 products by units sold in the last 90 days (cancelled orders excluded). */
    public function isBestSeller(Product $product): bool
    {
        $this->bestSellers ??= OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->where('orders.placed_at', '>=', now()->subDays(90))
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])
            ->groupBy('product_variants.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit(10)
            ->pluck('product_variants.product_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();

        return isset($this->bestSellers[$product->getKey()]);
    }

    /** Ids of the best sellers, best first. */
    public function bestSellerIds(): array
    {
        $this->isBestSeller(new Product);

        return array_keys($this->bestSellers ?? []);
    }
}

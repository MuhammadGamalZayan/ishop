<?php

namespace App\Support\Storefront;

use App\Enums\AttributeType;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Everything the product page needs, as plain arrays.
 *
 * Stock is never sent as a number unless it is low (≤ 3 or the variant's
 * alert level): "Only 2 left" helps the shopper, an exact inventory count
 * would only help competitors.
 */
class ProductPage
{
    public function __construct(private readonly Catalog $catalog) {}

    public function data(Product $product): array
    {
        $variants = $product->variants;
        $attributes = $this->attributes($variants);
        $category = $product->categories->first();

        return [
            'id' => $product->getKey(),
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description ?: null,
            'description' => $product->description ?: null,
            'brand' => $product->brand?->name,
            'meta_title' => $product->meta_title ?: $product->name,
            'meta_description' => $product->meta_description ?: ($product->short_description ?: null),
            'breadcrumb' => $category ? ['name' => $category->name, 'url' => route('shop.category', ['slug' => $category->slug])] : null,
            'categories' => $product->categories->map(fn ($c): array => ['name' => $c->name, 'url' => route('shop.category', ['slug' => $c->slug])])->values()->all(),
            'badge' => $this->catalog->isBestSeller($product) ? 'best' : null,
            'images' => $this->images($product, 'images'),
            'colour_images' => $this->colourImages($product, $attributes),
            'attributes' => $attributes,
            'variants' => $variants->map(fn (ProductVariant $v): array => $this->variant($v))->values()->all(),
            'default_variant_id' => ($variants->firstWhere('is_default', true) ?? $variants->first())->getKey(),
            'related' => $this->related($product),
        ];
    }

    /** The subset the quick-add window needs (no description, no suggestions). */
    public function quick(Product $product): array
    {
        $variants = $product->variants;
        $attributes = $this->attributes($variants);

        return [
            'name' => $product->name,
            'url' => route('shop.product', ['slug' => $product->slug]),
            'images' => $this->images($product, 'images'),
            'colour_images' => $this->colourImages($product, $attributes),
            'attributes' => $attributes,
            'variants' => $variants->map(fn (ProductVariant $v): array => $this->variant($v))->values()->all(),
            'default_variant_id' => ($variants->firstWhere('is_default', true) ?? $variants->first())->getKey(),
        ];
    }

    private function variant(ProductVariant $variant): array
    {
        $inStock = $variant->isInStock();
        $lowAt = max(3, (int) $variant->low_stock_threshold);
        $low = $variant->track_inventory && $inStock && $variant->stock_quantity <= $lowAt;

        return [
            'id' => $variant->getKey(),
            'sku' => $variant->sku,
            'price' => $variant->price,
            'compare_at' => $variant->isOnSale() ? $variant->compare_at_price : null,
            'values' => $variant->attributeValues->pluck('id')->all(),
            'in_stock' => $inStock,
            'left' => $low ? $variant->stock_quantity : null,
        ];
    }

    /** Options actually used by this product's variants, in admin order. */
    private function attributes($variants): array
    {
        return $variants
            ->flatMap(fn (ProductVariant $v) => $v->attributeValues)
            ->unique('id')
            ->groupBy('attribute_id')
            ->map(function ($values): array {
                /** @var ProductAttribute $attribute */
                $attribute = $values->first()->productAttribute;

                return [
                    'id' => $attribute->getKey(),
                    'name' => $attribute->name,
                    'type' => $attribute->type->value,
                    'sort' => $attribute->sort_order,
                    'values' => $values->sortBy('sort_order')->map(fn (ProductAttributeValue $v): array => [
                        'id' => $v->getKey(),
                        'name' => $v->value,
                        'hex' => $v->color_hex,
                    ])->values()->all(),
                ];
            })
            ->sortBy('sort')
            ->values()
            ->all();
    }

    /** @return list<array{large: string, thumb: string}> */
    private function images(Product $product, string $collection): array
    {
        return $product->getMedia($collection)
            ->map(fn (Media $m): array => ['large' => $m->getUrl('large'), 'thumb' => $m->getUrl('thumb')])
            ->values()
            ->all();
    }

    /** colour value id => its images (shown first when that colour is chosen). */
    private function colourImages(Product $product, array $attributes): array
    {
        $out = [];

        foreach ($attributes as $attribute) {
            if ($attribute['type'] !== AttributeType::Color->value) {
                continue;
            }

            foreach ($attribute['values'] as $value) {
                $images = $this->images($product, 'color-'.$value['id']);
                if ($images !== []) {
                    $out[$value['id']] = $images;
                }
            }
        }

        return $out;
    }

    /** Hand-picked suggestions, else other products from the same categories. */
    private function related(Product $product): array
    {
        $picked = $this->catalog->cardQuery()
            ->whereHas('relatedTo', fn ($q) => $q->where('product_related.product_id', $product->getKey()))
            ->limit(4)
            ->get();

        if ($picked->isEmpty()) {
            $picked = $this->catalog->cardQuery()
                ->whereKeyNot($product->getKey())
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $product->categories->pluck('id')))
                ->latest('published_at')
                ->limit(4)
                ->get();
        }

        return $this->catalog->cards($picked);
    }
}

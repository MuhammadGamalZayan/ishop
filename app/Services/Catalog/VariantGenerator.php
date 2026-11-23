<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Builds a product's variants from chosen option values.
 *
 * Given Colour: [Black, Navy] and Size: [Cabin, Large] it creates the four
 * combinations, skipping any combination the product already has, so it is
 * safe to run again after adding a new colour. New variants start at the
 * given price with zero stock (stock only arrives through Inventory).
 */
class VariantGenerator
{
    public const MAX_VARIANTS = 100;

    /**
     * @param  array<int, list<int>>  $valueIdsByAttribute  attribute id => chosen value ids
     * @return Collection<int, ProductVariant> the variants that were created
     */
    public function generate(Product $product, array $valueIdsByAttribute, int $price, ?string $skuPrefix = null): Collection
    {
        if ($price < 1) {
            throw new InvalidArgumentException('Price must be at least one piastre.');
        }

        $groups = $this->loadGroups($valueIdsByAttribute);
        $combinations = $this->combinations($groups);

        if (count($combinations) > self::MAX_VARIANTS) {
            throw new InvalidArgumentException('Too many combinations: '.count($combinations).' (max '.self::MAX_VARIANTS.').');
        }

        $prefix = $this->prefix($product, $skuPrefix);

        return DB::transaction(function () use ($product, $combinations, $price, $prefix): Collection {
            // Lock the product so two admins generating at once can't duplicate combinations.
            Product::query()->whereKey($product->getKey())->lockForUpdate()->first();

            $existing = $product->variants()->withTrashed()->with('attributeValues:id')->get()
                ->map(fn (ProductVariant $v): string => $this->key($v->attributeValues->pluck('id')->all()))
                ->flip();

            $hasDefault = $product->variants()->where('is_default', true)->exists();
            $created = collect();

            foreach ($combinations as $values) {
                $ids = array_map(fn (ProductAttributeValue $v): int => $v->getKey(), $values);

                if ($existing->has($this->key($ids))) {
                    continue;
                }

                $variant = $product->variants()->create([
                    'sku' => $this->uniqueSku($prefix.'-'.implode('-', array_map(fn (ProductAttributeValue $v): string => strtoupper($v->slug), $values))),
                    'price' => $price,
                    'is_default' => ! $hasDefault && $created->isEmpty(),
                    'is_active' => true,
                    'track_inventory' => true,
                ]);
                $variant->attributeValues()->attach($ids);

                $created->push($variant);
            }

            return $created;
        }, attempts: 3);
    }

    /**
     * @param  array<int, list<int>>  $valueIdsByAttribute
     * @return list<list<ProductAttributeValue>>
     */
    private function loadGroups(array $valueIdsByAttribute): array
    {
        $groups = [];

        foreach ($valueIdsByAttribute as $attributeId => $valueIds) {
            $valueIds = array_values(array_unique(array_map('intval', $valueIds)));

            if ($valueIds === []) {
                continue;
            }

            $values = ProductAttributeValue::query()
                ->where('attribute_id', $attributeId)
                ->whereKey($valueIds)
                ->orderBy('sort_order')
                ->get();

            // Every id must belong to the attribute it was listed under.
            if ($values->count() !== count($valueIds)) {
                throw new InvalidArgumentException("Some values do not belong to attribute {$attributeId}.");
            }

            $groups[] = $values->all();
        }

        if ($groups === []) {
            throw new InvalidArgumentException('Choose at least one option value.');
        }

        return $groups;
    }

    /**
     * @param  list<list<ProductAttributeValue>>  $groups
     * @return list<list<ProductAttributeValue>>
     */
    private function combinations(array $groups): array
    {
        $result = [[]];

        foreach ($groups as $values) {
            $next = [];
            foreach ($result as $partial) {
                foreach ($values as $value) {
                    $next[] = [...$partial, $value];
                }
            }
            $result = $next;
        }

        return $result;
    }

    /** @param  list<int>  $ids */
    private function key(array $ids): string
    {
        sort($ids);

        return implode(',', $ids);
    }

    private function prefix(Product $product, ?string $skuPrefix): string
    {
        $prefix = $skuPrefix ?: Str::slug((string) $product->getTranslation('name', 'en', false));

        return strtoupper(Str::limit(Str::slug($prefix), 30, '')) ?: 'P'.$product->getKey();
    }

    private function uniqueSku(string $sku): string
    {
        $sku = Str::limit($sku, 60, '');
        $candidate = $sku;

        for ($i = 2; ProductVariant::withTrashed()->where('sku', $candidate)->exists(); $i++) {
            $candidate = $sku.'-'.$i;
        }

        return $candidate;
    }
}

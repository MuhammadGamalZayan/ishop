<?php

namespace App\Models;

use App\Exceptions\ModelInUseException;
use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** One option value such as Red or XL. */
#[Fillable(['attribute_id', 'value', 'slug', 'color_hex', 'sort_order'])]
class ProductAttributeValue extends Model
{
    use GeneratesUniqueSlug, HasTranslations;

    protected function slugSource(): string
    {
        return 'value';
    }

    /** Value slugs are unique inside their attribute (colour=black, strap=black). */
    protected function slugScope(Builder $query): void
    {
        $query->where('attribute_id', $this->attribute_id);
    }

    protected $table = 'attribute_values';

    /** @var list<string> */
    public array $translatable = ['value'];

    protected static function booted(): void
    {
        static::saving(function (ProductAttributeValue $value): void {
            if ($value->color_hex !== null) {
                $value->color_hex = strtolower($value->color_hex);
            }
        });

        static::deleting(function (ProductAttributeValue $value): void {
            if ($value->variants()->exists()) {
                throw ModelInUseException::because('admin.attributes.errors.value_in_use');
            }
        });
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<ProductAttribute, $this> */
    public function productAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }

    /** @return BelongsToMany<ProductVariant, $this> */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'attribute_value_product_variant', 'attribute_value_id', 'product_variant_id');
    }
}

<?php

namespace App\Models;

use App\Enums\AttributeType;
use App\Exceptions\ModelInUseException;
use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An option type such as Colour or Size. Named ProductAttribute because
 * "Attribute" clashes with Eloquent's own attribute API.
 */
#[Fillable(['name', 'slug', 'type', 'sort_order'])]
class ProductAttribute extends Model
{
    use GeneratesUniqueSlug, HasTranslations;

    protected function slugSource(): string
    {
        return 'name';
    }

    protected $table = 'attributes';

    /** @var list<string> */
    public array $translatable = ['name'];

    protected static function booted(): void
    {
        // Deleting an attribute cascades to its values in the database, which
        // would fail (or orphan variants) if any value is still in use.
        static::deleting(function (ProductAttribute $attribute): void {
            if ($attribute->values()->whereHas('variants')->exists()) {
                throw ModelInUseException::because('admin.attributes.errors.in_use');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<ProductAttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id')->orderBy('sort_order');
    }
}

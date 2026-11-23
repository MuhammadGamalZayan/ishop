<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\HasProductImages;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable([
    'brand_id', 'name', 'slug', 'short_description', 'description', 'status',
    'is_featured', 'published_at', 'meta_title', 'meta_description',
])]
class Product extends Model implements HasMedia
{
    use HasProductImages, HasSlug, HasTranslations, LogsActivity, SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'short_description', 'description', 'meta_title', 'meta_description'];

    protected static function booted(): void
    {
        static::saving(fn (Product $product) => $product->search_text = $product->buildSearchText());
    }

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Product $product): string => (string) $product->getTranslation('name', 'en', false))
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Both languages + every SKU, for the FULLTEXT index. */
    public function buildSearchText(): string
    {
        $parts = [
            ...array_values($this->getTranslations('name')),
            ...array_values($this->getTranslations('short_description')),
        ];

        if ($this->exists) {
            $parts = [...$parts, ...$this->variants()->pluck('sku')->all()];
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Hand-picked suggestions, in the order the admin set.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_related', 'product_id', 'related_product_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * The other direction: products that list this one as a suggestion.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function relatedTo(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_related', 'related_product_id', 'product_id');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasOne<ProductVariant, $this> */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /** Same rule as scopePublished(), for a loaded model. */
    public function isPublished(): bool
    {
        return ! $this->trashed()
            && $this->status === ProductStatus::Active
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /** Visible on the storefront right now. @param Builder<self> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}

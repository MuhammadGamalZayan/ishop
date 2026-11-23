<?php

namespace App\Models;

use App\Exceptions\ModelInUseException;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
class Brand extends Model implements HasMedia
{
    use HasSlug, HasTranslations, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['description'];

    protected static function booted(): void
    {
        // Soft deletes don't trigger the FK's nullOnDelete, so a trashed brand
        // would stay attached to live products. Products must be moved first.
        static::deleting(function (Brand $brand): void {
            if ($brand->products()->exists()) {
                throw ModelInUseException::because('admin.brands.errors.has_products');
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            // No SVG: an uploaded SVG can carry scripts and is served from our own domain.
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}

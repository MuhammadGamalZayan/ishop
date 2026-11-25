<?php

namespace App\Models;

use App\Exceptions\CategoryInUseException;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'is_active', 'show_in_menu', 'menu_colour_value_ids', 'menu_cards', 'sort_order', 'meta_title', 'meta_description'])]
class Category extends Model implements HasMedia
{
    use HasSlug, HasTranslations, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    protected static function booted(): void
    {
        // Category URLs sit at the root (/en/{slug}); never let one shadow another page.
        static::saving(function (Category $category): void {
            if (in_array($category->slug, config('ishop.reserved_slugs'), true)) {
                $category->slug = $category->slug.'-'.Str::lower(Str::random(4));
            }
        });

        // Last line of defence; the admin panel checks the same rules first
        // so the user gets a friendly message instead of an exception.
        static::deleting(function (Category $category): void {
            if ($category->children()->exists()) {
                throw CategoryInUseException::hasChildren();
            }

            if ($category->products()->exists()) {
                throw CategoryInUseException::hasProducts();
            }
        });

        static::restoring(function (Category $category): void {
            if ($category->parent_id !== null && $category->parent()->withTrashed()->first()?->trashed()) {
                throw CategoryInUseException::parentTrashed();
            }
        });
    }

    /**
     * Ids of every category below this one, at any depth.
     * Used to stop a category being moved under itself.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $level = [$this->getKey()];

        while ($level !== []) {
            $level = self::withTrashed()->whereIn('parent_id', $level)->pluck('id')->all();
            $level = array_values(array_diff($level, $ids));
            array_push($ids, ...$level);
        }

        return $ids;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
            'menu_colour_value_ids' => 'array',
            'menu_cards' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Category $category): string => (string) $category->getTranslation('name', 'en', false))
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}

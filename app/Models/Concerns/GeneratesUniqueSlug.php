<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Fills an empty `slug` from the English text of a translatable attribute,
 * adding -2, -3… when that slug is taken. A slug typed by the admin is left
 * alone (the form validates it as unique).
 *
 * The using model defines slugSource() and, if slugs are unique only inside
 * a parent, slugScope().
 */
trait GeneratesUniqueSlug
{
    abstract protected function slugSource(): string;

    /** @param  Builder<static>  $query */
    protected function slugScope(Builder $query): void
    {
        //
    }

    public static function bootGeneratesUniqueSlug(): void
    {
        static::saving(function (self $model): void {
            if (filled($model->slug)) {
                return;
            }

            $base = Str::slug((string) $model->getTranslation($model->slugSource(), 'en', false)) ?: 'item';
            $slug = $base;

            for ($i = 2; $model->slugTaken($slug); $i++) {
                $slug = "{$base}-{$i}";
            }

            $model->slug = $slug;
        });
    }

    private function slugTaken(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);
        $this->slugScope($query);

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
    }
}

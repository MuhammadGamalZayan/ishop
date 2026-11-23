<?php

namespace App\Models\Concerns;

use Spatie\Translatable\HasTranslations as SpatieHasTranslations;

/**
 * spatie/laravel-translatable with two fixes for form saving:
 *
 * 1. Assigning an array REPLACES all translations. Spatie merges instead, so
 *    clearing the Arabic text on edit would silently keep the old Arabic text.
 * 2. Empty values are dropped, and an attribute with no translations left is
 *    stored as NULL instead of {"en": null}.
 *
 * Use this trait instead of Spatie's on every translatable model.
 */
trait HasTranslations
{
    use SpatieHasTranslations {
        SpatieHasTranslations::setAttribute as setSpatieAttribute;
    }

    public function setAttribute($key, $value)
    {
        if (! $this->isTranslatableAttribute($key) || ! (is_array($value) || $value === null)) {
            return $this->setSpatieAttribute($key, $value);
        }

        $translations = array_filter(
            (array) $value,
            fn ($translation): bool => $translation !== null && $translation !== '',
        );

        // Start from empty so the new array replaces, rather than merges into, the old one.
        $this->attributes[$key] = null;

        return $translations === [] ? $this : $this->setSpatieAttribute($key, $translations);
    }
}

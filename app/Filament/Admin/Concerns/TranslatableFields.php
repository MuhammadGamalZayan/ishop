<?php

namespace App\Filament\Admin\Concerns;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;

/**
 * Helpers for spatie/laravel-translatable fields in Filament forms.
 *
 * A translatable attribute `name` is edited as `name.en` and `name.ar`,
 * shown in one tab per language. Filling and saving work with the full
 * translations array instead of the single string for the current locale.
 */
final class TranslatableFields
{
    /** @return list<string> */
    public static function locales(): array
    {
        return config('ishop.locales');
    }

    /**
     * One tab per language; $fields builds that tab's fields for a locale.
     *
     * @param  callable(string $locale): list<Field>  $fields
     */
    public static function tabs(string $id, callable $fields): Tabs
    {
        return Tabs::make($id)
            ->tabs(array_map(
                fn (string $locale): Tab => Tab::make(__("admin.locales.{$locale}"))
                    ->schema($fields($locale)),
                self::locales(),
            ));
    }

    /**
     * Table search over a JSON translations column, in every language,
     * case-insensitively. Works on MySQL and MariaDB.
     *
     * @return Closure(Builder, string): Builder
     */
    public static function search(string $column): Closure
    {
        return fn (Builder $query, string $search): Builder => $query->where(function (Builder $q) use ($column, $search): void {
            foreach (self::locales() as $locale) {
                $q->orWhereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$locale}\"'))) LIKE ?",
                    ['%'.mb_strtolower($search).'%'],
                );
            }
        });
    }

    /**
     * Replace each translatable attribute with all its translations,
     * so `name.en` / `name.ar` inputs are filled on the edit page.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(Model $record, array $data): array
    {
        foreach ($record->getTranslatableAttributes() as $attribute) {
            $data[$attribute] = $record->getTranslations($attribute);
        }

        return $data;
    }

    /**
     * Drop empty translations before saving, so an optional field left blank
     * is stored as NULL rather than {"en": null, "ar": null}.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $attributes
     * @return array<string, mixed>
     */
    public static function clean(array $data, array $attributes): array
    {
        foreach ($attributes as $attribute) {
            if (! is_array($data[$attribute] ?? null)) {
                continue;
            }

            $values = array_filter(
                array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data[$attribute]),
                fn ($value): bool => $value !== null && $value !== '',
            );

            $data[$attribute] = $values === [] ? null : $values;
        }

        return $data;
    }
}

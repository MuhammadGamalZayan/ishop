<?php

namespace App\Filament\Admin\Support;

use Closure;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * A URL slug field: optional on create (the model builds it from the English
 * name), required on edit, lowercase-dash format, unique in its table.
 * $scope narrows uniqueness, e.g. option values are unique per attribute.
 */
final class SlugInput
{
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @param  (Closure(Unique, ?Model): Unique)|null  $scope */
    public static function make(string $table, ?Closure $scope = null): TextInput
    {
        return TextInput::make('slug')
            ->label(__('admin.common.slug'))
            ->maxLength(120)
            ->required(fn (string $operation): bool => $operation === 'edit')
            ->helperText(fn (string $operation): string => $operation === 'edit'
                ? __('admin.common.slug_edit')
                : __('admin.common.slug_create'))
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtolower(trim($state)) : null)
            ->regex(self::PATTERN)
            ->validationMessages(['regex' => __('admin.common.slug_format')])
            ->rule(function (?Model $record) use ($table, $scope): Unique {
                $rule = Rule::unique($table, 'slug')->ignore($record);

                return $scope ? $scope($rule, $record) : $rule;
            });
    }
}

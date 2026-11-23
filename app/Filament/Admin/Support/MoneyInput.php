<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\TextInput;

/**
 * Money is stored as integer piastres (1,250.00 EGP = 125000) and typed by
 * the admin in pounds with up to two decimals. Converting here keeps every
 * price field consistent and avoids float values reaching the database.
 */
final class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->inputMode('decimal')
            ->step('0.01')
            ->minValue(0)
            ->maxValue(9_999_999)
            ->suffix(config('ishop.currency'))
            ->rule('regex:/^\d+(\.\d{1,2})?$/')
            ->formatStateUsing(fn ($state): ?string => is_numeric($state) ? number_format(((int) $state) / 100, 2, '.', '') : $state)
            ->dehydrateStateUsing(fn ($state): ?int => self::toPiastres($state));
    }

    public static function toPiastres(mixed $state): ?int
    {
        if ($state === null || $state === '') {
            return null;
        }

        // String maths, not floats: "19.99" must become 1999 exactly.
        [$pounds, $fraction] = array_pad(explode('.', (string) $state, 2), 2, '0');

        return ((int) $pounds) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}

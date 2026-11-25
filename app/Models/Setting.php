<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One JSON value per key, edited in the admin panel (home page sections,
 * About menu cards…). Read through get(), which caches until the next save.
 *
 * @property string $key
 * @property array<string, mixed>|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $s) => Cache::forget("setting:{$s->key}"));
        static::deleted(fn (self $s) => Cache::forget("setting:{$s->key}"));
    }

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        return Cache::rememberForever("setting:{$key}", fn (): array => static::query()->where('key', $key)->value('value') ?? []);
    }

    /** @param array<string, mixed> $value */
    public static function put(string $key, array $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

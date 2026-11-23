<?php

namespace App\Models;

use App\Exceptions\ModelInUseException;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['name', 'is_active', 'sort_order'])]
class Governorate extends Model
{
    use HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected static function booted(): void
    {
        static::deleting(function (Governorate $governorate): void {
            if ($governorate->shippingAreas()->exists() || Address::query()->where('governorate_id', $governorate->getKey())->exists()) {
                throw ModelInUseException::because('admin.shipping.errors.governorate_in_use');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<ShippingArea, $this> */
    public function shippingAreas(): HasMany
    {
        return $this->hasMany(ShippingArea::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}

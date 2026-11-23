<?php

namespace App\Models;

use App\Exceptions\ModelInUseException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasTranslations;

/**
 * @property int $shipping_price piastres
 */
#[Fillable(['governorate_id', 'name', 'shipping_price', 'is_active', 'sort_order'])]
class ShippingArea extends Model
{
    use HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected static function booted(): void
    {
        // Customer address books point at areas; turn the area off instead.
        static::deleting(function (ShippingArea $area): void {
            if (Address::query()->where('shipping_area_id', $area->getKey())->exists()) {
                throw ModelInUseException::because('admin.shipping.errors.area_in_use');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'shipping_price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Governorate, $this> */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
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

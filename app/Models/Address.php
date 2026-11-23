<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'label', 'first_name', 'last_name', 'phone', 'governorate_id', 'shipping_area_id',
    'street', 'building', 'floor', 'apartment', 'landmark', 'is_default',
])]
class Address extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Governorate, $this> */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /** @return BelongsTo<ShippingArea, $this> */
    public function shippingArea(): BelongsTo
    {
        return $this->belongsTo(ShippingArea::class);
    }
}

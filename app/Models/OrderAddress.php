<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasTranslations;

class OrderAddress extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['governorate_name', 'area_name'];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

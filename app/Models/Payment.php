<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment attempt. Written only by App\Services\Payments.
 *
 * @property PaymentMethod $gateway
 * @property PaymentAttemptStatus $status
 * @property int $amount piastres
 * @property string|null $gateway_order_id
 */
class Payment extends Model
{
    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentMethod::class,
            'status' => PaymentAttemptStatus::class,
            'amount' => 'integer',
            'payload' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

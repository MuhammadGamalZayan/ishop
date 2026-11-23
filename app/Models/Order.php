<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Frozen snapshot of a purchase. Nothing here is mass-assignable: orders are
 * created only by App\Services\Checkout and changed only by App\Services\Orders.
 *
 * @property int $subtotal piastres
 * @property int $discount_total piastres
 * @property int $shipping_total piastres
 * @property int $tax_total piastres, already inside grand_total
 * @property int $grand_total piastres
 */
class Order extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'payment_expires_at' => 'datetime',
            'placed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<OrderAddress, $this> */
    public function address(): HasOne
    {
        return $this->hasOne(OrderAddress::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** Customer may start (another) online payment attempt for this order. */
    public function isAwaitingOnlinePayment(): bool
    {
        return $this->status === OrderStatus::Pending
            && $this->payment_method->isOnline()
            && in_array($this->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Pending, PaymentStatus::Failed], true)
            && ($this->payment_expires_at === null || $this->payment_expires_at->isFuture());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'payment_status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}

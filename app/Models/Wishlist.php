<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Owned by a user or by a guest token; never mass-assigned from a request. */
class Wishlist extends Model
{
    use MassPrunable;

    /** Guest wishlists nobody touched for `ishop.guest_cart_days`. Run by model:prune. */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('token')->where('expires_at', '<', now());
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WishlistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }
}

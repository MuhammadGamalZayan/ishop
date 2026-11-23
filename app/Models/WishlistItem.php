<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id'])]
class WishlistItem extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Wishlist, $this> */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

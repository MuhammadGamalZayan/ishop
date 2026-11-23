<?php

namespace App\Services\Cart;

/** Why a cart line can't be bought as it is. */
enum CartIssue: string
{
    /** The product was deleted, archived or unpublished. */
    case Removed = 'removed';

    /** The product is still sold, but not this colour / size (variant deleted or turned off). */
    case VariantGone = 'variant_gone';

    /** None left. */
    case SoldOut = 'sold_out';

    /** Fewer left than the quantity in the cart. */
    case OnlyLeft = 'only_left';

    /** The customer must remove the line (true) or can just lower the quantity (false). */
    public function mustRemove(): bool
    {
        return $this !== self::OnlyLeft;
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Something the customer asked of their cart or wishlist can't be done.
 * The message is translated and safe to show; bootstrap/app.php turns it
 * into a toast (or a 422 for JSON requests).
 */
class CartException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self(__('cart.errors.unavailable'));
    }

    public static function outOfStock(): self
    {
        return new self(__('cart.errors.out_of_stock'));
    }

    public static function onlyLeft(int $available, int $inCart): self
    {
        return new self($inCart > 0
            ? __('cart.errors.only_left_in_cart', ['count' => $available, 'in_cart' => $inCart])
            : __('cart.errors.only_left', ['count' => $available]));
    }

    public static function maxQuantity(int $max): self
    {
        return new self(__('cart.errors.max_quantity', ['max' => $max]));
    }

    public static function tooManyLines(): self
    {
        return new self(__('cart.errors.too_many_lines'));
    }

    public static function lineNotFound(): self
    {
        return new self(__('cart.errors.line_not_found'));
    }

    public static function wishlistFull(): self
    {
        return new self(__('cart.errors.wishlist_full'));
    }
}

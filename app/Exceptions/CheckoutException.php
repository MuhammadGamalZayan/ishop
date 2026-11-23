<?php

namespace App\Exceptions;

use App\Services\Checkout\Quote;
use RuntimeException;

/**
 * The order can't be placed as it stands. The message is translated and
 * safe to show. For a price change, $quote carries the new totals so the
 * page can show them before the customer confirms again.
 */
class CheckoutException extends RuntimeException
{
    public ?Quote $quote = null;

    public static function emptyCart(): self
    {
        return new self(__('checkout.errors.empty_cart'));
    }

    public static function unavailable(string $product): self
    {
        return new self(__('checkout.errors.unavailable', ['product' => $product]));
    }

    public static function outOfStock(string $product, int $available): self
    {
        return new self($available > 0
            ? __('checkout.errors.only_left', ['product' => $product, 'count' => $available])
            : __('checkout.errors.out_of_stock', ['product' => $product]));
    }

    public static function shippingArea(): self
    {
        return new self(__('checkout.errors.shipping_area'));
    }

    public static function address(): self
    {
        return new self(__('checkout.errors.address'));
    }

    public static function paymentMethod(): self
    {
        return new self(__('checkout.errors.payment_method'));
    }

    public static function priceChanged(Quote $quote): self
    {
        $e = new self(__('checkout.errors.price_changed'));
        $e->quote = $quote;

        return $e;
    }

    public static function keyReused(): self
    {
        return new self(__('checkout.errors.key_reused'));
    }
}

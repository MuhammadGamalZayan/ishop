<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A stock change would take a variant below zero. Carries the variant and
 * what is really available, so callers (checkout, admin) can tell the user.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $variantId,
        public readonly string $sku,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(__('inventory.insufficient', [
            'sku' => $sku,
            'available' => $available,
            'requested' => $requested,
        ]));
    }
}

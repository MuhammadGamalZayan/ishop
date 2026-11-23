<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by a model's deleting event when the record is still referenced
 * (a brand with products, an option value used by variants…). The admin
 * panel checks the same thing first and shows the message as a notice.
 */
class ModelInUseException extends RuntimeException
{
    public static function because(string $translationKey): self
    {
        return new self(__($translationKey));
    }
}

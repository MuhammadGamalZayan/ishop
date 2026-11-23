<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a category change would break the tree or orphan products:
 * deleting one that still has subcategories or products, or restoring one
 * whose parent is in the trash.
 */
class CategoryInUseException extends RuntimeException
{
    public static function hasChildren(): self
    {
        return new self(__('admin.categories.errors.has_children'));
    }

    public static function hasProducts(): self
    {
        return new self(__('admin.categories.errors.has_products'));
    }

    public static function parentTrashed(): self
    {
        return new self(__('admin.categories.errors.parent_trashed'));
    }
}

<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

/**
 * Variants are edited as part of their product. Stock is a separate right:
 * see adjustStock / viewStock, used by the variant table's stock actions.
 */
class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.update');
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.update');
    }

    public function restore(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.update');
    }

    public function forceDelete(User $user, ProductVariant $variant): bool
    {
        return false;
    }

    public function adjustStock(User $user, ProductVariant $variant): bool
    {
        return $user->can('inventory.adjust');
    }

    public function viewStock(User $user, ProductVariant $variant): bool
    {
        return $user->can('inventory.view');
    }
}

<?php

namespace App\Policies;

use App\Models\ProductAttribute;
use App\Models\User;

/**
 * Attributes are part of setting up products, so they use the products.*
 * permissions instead of a separate set.
 */
class ProductAttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('products.update');
    }

    public function reorder(User $user): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('products.delete');
    }
}

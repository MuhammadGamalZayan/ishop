<?php

namespace App\Policies;

use App\Models\ProductAttributeValue;
use App\Models\User;

/** Option values are edited as part of their attribute. */
class ProductAttributeValuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductAttributeValue $value): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.update');
    }

    public function update(User $user, ProductAttributeValue $value): bool
    {
        return $user->can('products.update');
    }

    public function reorder(User $user): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, ProductAttributeValue $value): bool
    {
        return $user->can('products.update');
    }
}

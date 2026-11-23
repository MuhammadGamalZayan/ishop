<?php

namespace App\Policies;

use App\Models\Governorate;
use App\Models\User;

/** Delivery zones use the shipping.* permissions. */
class GovernoratePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipping.view');
    }

    public function view(User $user, Governorate $governorate): bool
    {
        return $user->can('shipping.view');
    }

    public function create(User $user): bool
    {
        return $user->can('shipping.update');
    }

    public function update(User $user, Governorate $governorate): bool
    {
        return $user->can('shipping.update');
    }

    public function reorder(User $user): bool
    {
        return $user->can('shipping.update');
    }

    public function delete(User $user, Governorate $governorate): bool
    {
        return $user->can('shipping.update');
    }
}

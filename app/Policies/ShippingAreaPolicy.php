<?php

namespace App\Policies;

use App\Models\ShippingArea;
use App\Models\User;

class ShippingAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipping.view');
    }

    public function view(User $user, ShippingArea $area): bool
    {
        return $user->can('shipping.view');
    }

    public function create(User $user): bool
    {
        return $user->can('shipping.update');
    }

    public function update(User $user, ShippingArea $area): bool
    {
        return $user->can('shipping.update');
    }

    public function reorder(User $user): bool
    {
        return $user->can('shipping.update');
    }

    public function delete(User $user, ShippingArea $area): bool
    {
        return $user->can('shipping.update');
    }
}

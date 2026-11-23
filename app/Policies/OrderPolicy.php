<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Staff read orders and move their status; nobody creates, edits or deletes
 * an order from the panel (they are records of what was sold).
 * Customers see their own orders through the storefront, not this policy's
 * admin abilities: see view().
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view') || $order->user_id === $user->getKey();
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->can('orders.update-status');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Admin access to categories, backed by spatie/laravel-permission.
 * super-admin passes everything through Gate::before in AppServiceProvider,
 * except forceDelete, which the panel never offers.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.update');
    }

    public function reorder(User $user): bool
    {
        return $user->can('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.delete');
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can('categories.delete');
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return false;
    }
}

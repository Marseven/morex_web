<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function view(User $user, Category $category): bool
    {
        return $category->is_system || $user->id === $category->user_id;
    }

    public function update(User $user, Category $category): bool
    {
        // System categories are read-only (only budget_limit can be customized via BudgetSettings)
        if ($category->is_system) {
            return false;
        }
        return $user->id === $category->user_id;
    }

    public function delete(User $user, Category $category): bool
    {
        // Cannot delete system categories
        return !$category->is_system && $user->id === $category->user_id;
    }
}

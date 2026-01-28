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
        // Allow updating system categories (name, budget, color, icon)
        // but they still belong to user through user_id or are shared (is_system)
        return $category->is_system || $user->id === $category->user_id;
    }

    public function delete(User $user, Category $category): bool
    {
        // Cannot delete system categories
        return !$category->is_system && $user->id === $category->user_id;
    }
}

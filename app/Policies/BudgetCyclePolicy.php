<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BudgetCycle;
use App\Models\User;

final class BudgetCyclePolicy
{
    public function view(User $user, BudgetCycle $cycle): bool
    {
        return (int) $user->id === (int) $cycle->user_id;
    }
}

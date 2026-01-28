<?php

namespace App\Policies;

use App\Models\RecurringTransaction;
use App\Models\User;

class RecurringTransactionPolicy
{
    public function view(User $user, RecurringTransaction $recurringTransaction): bool
    {
        return $user->id === $recurringTransaction->user_id;
    }

    public function update(User $user, RecurringTransaction $recurringTransaction): bool
    {
        return $user->id === $recurringTransaction->user_id;
    }

    public function delete(User $user, RecurringTransaction $recurringTransaction): bool
    {
        return $user->id === $recurringTransaction->user_id;
    }
}

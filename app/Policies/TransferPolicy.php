<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function view(User $user, Transfer $transfer): bool
    {
        return $user->id === $transfer->user_id;
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $user->id === $transfer->user_id;
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return $user->id === $transfer->user_id;
    }
}

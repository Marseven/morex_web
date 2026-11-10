<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlannedPurchase;
use App\Models\User;

final class PlannedPurchasePolicy
{
    public function update(User $user, PlannedPurchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }

    public function delete(User $user, PlannedPurchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }
}

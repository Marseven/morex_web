<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

/** Envoi de notifications push aux navigateurs abonnés d'un utilisateur. */
interface PushNotifier
{
    /**
     * @param  array{title: string, body: string, url?: string, tag?: string}  $payload
     * @return int nombre de notifications acceptées par les services push
     */
    public function sendToUser(User $user, array $payload): int;
}

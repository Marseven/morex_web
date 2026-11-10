<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\Transaction;
use App\Services\WebPushNotifier;
use Illuminate\Console\Command;

class SendDailyReminders extends Command
{
    protected $signature = 'reminders:daily';

    protected $description = 'Envoie un rappel Web Push aux utilisateurs qui n\'ont pas saisi de transaction aujourd\'hui';

    public function handle(): int
    {
        if (! config('webpush.public_key') || ! config('webpush.private_key')) {
            $this->warn('Clés VAPID non configurées — rappels ignorés.');

            return self::SUCCESS;
        }

        $notifier = app(WebPushNotifier::class);
        $webPush = $notifier->client(); // envoi partagé avec les alertes de plafond (timeout court)

        $payload = json_encode([
            'title' => 'MR Money',
            'body' => "N'oubliez pas de noter vos dépenses du jour.",
            'url' => '/transactions/create',
            'tag' => 'daily-reminder',
        ]);

        $sent = 0;

        // Un enregistrement par navigateur ; on ne notifie que les utilisateurs sans saisie aujourd'hui.
        PushSubscription::with('user')->chunk(100, function ($subscriptions) use ($notifier, $webPush, $payload, &$sent) {
            foreach ($subscriptions as $sub) {
                if (! $sub->user) {
                    continue;
                }

                $hasLoggedToday = Transaction::where('user_id', $sub->user_id)
                    ->whereDate('created_at', today())
                    ->exists();

                if ($hasLoggedToday) {
                    continue;
                }

                $webPush->queueNotification($notifier->subscription($sub), $payload);
                $sent++;
            }

            // Envoi + nettoyage des abonnements expirés (410/404).
            $notifier->flush($webPush);
        });

        $this->info("Rappels envoyés : {$sent}");

        return self::SUCCESS;
    }
}

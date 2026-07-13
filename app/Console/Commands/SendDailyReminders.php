<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendDailyReminders extends Command
{
    protected $signature = 'reminders:daily';

    protected $description = 'Envoie un rappel Web Push aux utilisateurs qui n\'ont pas saisi de transaction aujourd\'hui';

    public function handle(): int
    {
        if (!config('webpush.public_key') || !config('webpush.private_key')) {
            $this->warn('Clés VAPID non configurées — rappels ignorés.');
            return self::SUCCESS;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ]);

        $payload = json_encode([
            'title' => 'MR Money',
            'body' => "N'oubliez pas de noter vos dépenses du jour.",
            'url' => '/transactions/create',
            'tag' => 'daily-reminder',
        ]);

        $sent = 0;

        // Un enregistrement par navigateur ; on ne notifie que les utilisateurs sans saisie aujourd'hui.
        PushSubscription::with('user')->chunk(100, function ($subscriptions) use ($webPush, $payload, &$sent) {
            foreach ($subscriptions as $sub) {
                if (!$sub->user) {
                    continue;
                }

                $hasLoggedToday = Transaction::where('user_id', $sub->user_id)
                    ->whereDate('created_at', today())
                    ->exists();

                if ($hasLoggedToday) {
                    continue;
                }

                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys' => ['p256dh' => $sub->public_key, 'auth' => $sub->auth_token],
                    ]),
                    $payload
                );
                $sent++;
            }

            // Envoi + nettoyage des abonnements expirés (410/404).
            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    $code = $report->getResponse()?->getStatusCode();
                    if (in_array($code, [404, 410], true)) {
                        PushSubscription::where('endpoint', $report->getRequest()->getUri()->__toString())->delete();
                    }
                }
            }
        });

        $this->info("Rappels envoyés : {$sent}");
        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PushNotifier;
use App\Models\PushSubscription;
use App\Models\User;
use App\Rules\KnownPushEndpoint;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Web Push (VAPID). Sans clés configurées, n'envoie rien. Les abonnements expirés (404/410) sont supprimés. */
final class WebPushNotifier implements PushNotifier
{
    public const TIMEOUT_SECONDS = 5;

    public function isConfigured(): bool
    {
        return (bool) config('webpush.public_key') && (bool) config('webpush.private_key');
    }

    public function sendToUser(User $user, array $payload): int
    {
        // Revérifié à l'envoi : un abonnement enregistré avant la règle ne doit jamais être appelé
        $subscriptions = PushSubscription::where('user_id', $user->id)->get()
            ->filter(fn (PushSubscription $sub) => KnownPushEndpoint::allows($sub->endpoint));
        if (! $this->isConfigured() || $subscriptions->isEmpty()) {
            return 0;
        }

        $webPush = $this->client();
        foreach ($subscriptions as $sub) {
            $webPush->queueNotification($this->subscription($sub), json_encode($payload + ['title' => 'MR Money']) ?: '{}');
        }

        return $this->flush($webPush, $user->id);
    }

    /** Client résolu par le conteneur (liaison dans AppServiceProvider), remplaçable en test. */
    public function client(): WebPush
    {
        return app(WebPush::class);
    }

    public static function makeClient(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ], [], self::TIMEOUT_SECONDS);
    }

    public function subscription(PushSubscription $sub): Subscription
    {
        return Subscription::create([
            'endpoint' => $sub->endpoint,
            'keys' => ['p256dh' => $sub->public_key, 'auth' => $sub->auth_token],
        ]);
    }

    /** Envoie la file et supprime les abonnements expirés ; renvoie le nombre de succès. */
    public function flush(WebPush $webPush, ?int $userId = null): int
    {
        $sent = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;

                continue;
            }
            if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                PushSubscription::where('endpoint', (string) $report->getRequest()->getUri())
                    ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
                    ->delete();
            }
        }

        return $sent;
    }
}

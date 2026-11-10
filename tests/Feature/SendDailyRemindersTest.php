<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\WebPush;
use Mockery;
use Tests\TestCase;

/** Rappel quotidien : utilise l'envoi push partagé (module plafonds-alertes). */
class SendDailyRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_reminder_through_the_shared_notifier(): void
    {
        config(['webpush.public_key' => 'pub', 'webpush.private_key' => 'priv']);
        $user = User::factory()->create();
        $sub = new PushSubscription(['endpoint' => 'https://push.example/abc', 'public_key' => 'k', 'auth_token' => 'a']);
        $sub->user_id = $user->id;
        $sub->save();

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once();
        $webPush->shouldReceive('flush')->once()->andReturn((function () {
            yield from [];
        })());
        $this->app->instance(WebPush::class, $webPush);

        $this->artisan('reminders:daily')->assertSuccessful()->expectsOutputToContain('Rappels envoyés : 1');
    }
}

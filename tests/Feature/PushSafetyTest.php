<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetAlert;
use App\Models\Category;
use App\Models\PushSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WebPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\WebPush;
use Mockery;
use Tests\TestCase;

/**
 * Revue de sécurité automatique (module plafonds-alertes) : isolation des alertes entre utilisateurs, SSRF push.
 */
class PushSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_on_a_shared_system_category_are_isolated_per_user(): void
    {
        $shared = Category::find(Category::BANK_FEES_ID);
        $shared->forceFill(['budget_limit' => 1000])->save();

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $account = Account::factory()->for($user)->withBalance(100000)->create();
            $tx = new Transaction(['amount' => 900, 'type' => 'expense', 'category_id' => $shared->id, 'account_id' => $account->id, 'date' => now()->toDateString()]);
            $tx->user_id = $user->id;
            $tx->save();
        }

        $this->assertSame(2, BudgetAlert::where('category_id', $shared->id)->count());
    }

    /** @return array<string, array{string}> */
    public static function forbiddenEndpoints(): array
    {
        return [
            'http' => ['http://fcm.googleapis.com/fcm/send/abc'],
            'hôte interne' => ['https://169.254.169.254/latest/meta-data'],
            'localhost' => ['https://localhost/push'],
            'hôte inconnu' => ['https://evil.example/push'],
            'suffixe piégé' => ['https://fcm.googleapis.com.evil.example/x'],
            'port explicite' => ['https://fcm.googleapis.com:443/x'],
            'utilisateur' => ['https://user@fcm.googleapis.com/x'],
            'point final' => ['https://fcm.googleapis.com./x'],
            'adresse IP' => ['https://142.250.0.1/x'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenEndpoints')]
    public function test_a_subscription_must_target_a_known_push_service(string $endpoint): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/push-subscriptions', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertUnprocessable();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_known_push_service_is_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertCreated();
    }

    public function test_a_stored_unknown_endpoint_is_never_called(): void
    {
        config(['webpush.public_key' => 'pub', 'webpush.private_key' => 'priv']);
        $user = User::factory()->create();
        $sub = new PushSubscription(['endpoint' => 'https://169.254.169.254/x', 'public_key' => 'k', 'auth_token' => 'a']);
        $sub->user_id = $user->id;
        $sub->save();

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldNotReceive('queueNotification');
        $webPush->shouldReceive('flush')->andReturn((function () {
            yield from [];
        })());
        $this->app->instance(WebPush::class, $webPush);

        $this->assertSame(0, app(WebPushNotifier::class)->sendToUser($user, ['title' => 't', 'body' => 'b']));
    }
}

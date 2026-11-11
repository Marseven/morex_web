<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SmsShortcutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Module sms-iphone (3b) : jeton réservé au Raccourci, SMS rapprochés par le moteur du module 3a.
 */
class SmsShortcutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $airtel;

    private const SMS = 'Paiement de 15500F DIGITECH pour achat. Nouveau solde 84500F. TID: PP261112.1400.A00001';

    /** SMS Airtel distinct (référence unique) pour les tests de volume. */
    private static function sms(int $n, int $amount = 100): string
    {
        return sprintf('Paiement de %dF MARCHAND pour achat. Nouveau solde 1F. TID: PP261112.1400.B%05d', $amount, $n);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(14, 0));
        $this->app->instance(PushNotifier::class, new class implements PushNotifier
        {
            public function sendToUser(User $user, array $payload): int
            {
                return 0;
            }
        });
        $this->user = User::factory()->create();
        $this->airtel = Account::factory()->for($this->user)->withBalance(100000)->create(['name' => 'Compte Airtel Money', 'is_default' => true]);
    }

    private function shortcutToken(): string
    {
        return app(SmsShortcutService::class)->issue($this->user);
    }

    private function postSms(string $token, string $message = self::SMS): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/webhook/sms', ['message' => $message, 'sender' => 'AirtelMoney']);
    }

    public function test_the_parser_reads_the_test_message(): void
    {
        $this->assertNotNull((new \App\Services\SmsParser)->parse(self::SMS, 'AirtelMoney'));
    }

    public function test_an_sms_matching_a_noted_operation_points_it_instead_of_duplicating(): void
    {
        $food = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        $noted = new Transaction(['amount' => 15500, 'type' => 'expense', 'category_id' => $food->id, 'account_id' => $this->airtel->id,
            'date' => now()->toDateString(), 'beneficiary' => 'restaurant']);
        $noted->user_id = $this->user->id;
        $noted->save();

        $this->postSms($this->shortcutToken())->assertOk()->assertJsonPath('matched', true);

        $this->assertSame(0, Transaction::where('status', Transaction::STATUS_PENDING)->count());
        $this->assertNotNull($noted->fresh()->reconciled_at);
        $this->assertSame('sms', StatementLine::sole()->source);
        $this->assertSame(84500, (int) $this->airtel->fresh()->balance); // compté une seule fois
    }

    public function test_an_sms_without_match_creates_a_pending_operation(): void
    {
        $this->postSms($this->shortcutToken())->assertCreated();

        $this->assertSame(1, Transaction::where('status', Transaction::STATUS_PENDING)->count());
    }

    public function test_the_shortcut_token_is_refused_everywhere_else(): void
    {
        $token = $this->shortcutToken();

        foreach (['/api/user', '/api/accounts', '/api/sync/pull'] as $route) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson($route)->assertForbidden();
        }
    }

    public function test_a_full_token_still_works_on_the_webhook(): void
    {
        $full = $this->user->createToken('api-token')->plainTextToken;

        $this->postSms($full)->assertCreated();
    }

    public function test_issuing_a_new_token_replaces_the_old_one(): void
    {
        $old = $this->shortcutToken();
        $new = $this->shortcutToken();

        $this->postSms($old)->assertUnauthorized();
        $this->postSms($new, self::sms(2, 2000))->assertCreated();
        $this->assertSame(1, $this->user->tokens()->where('name', SmsShortcutService::TOKEN_NAME)->count());
    }

    public function test_the_profile_issues_shows_once_then_revokes(): void
    {
        $this->actingAs($this->user)->post('/profile/sms-token')->assertSessionHasErrors('password');
        $this->actingAs($this->user)->post('/profile/sms-token', ['password' => 'password'])->assertRedirect()
            ->assertSessionHas('sms_token');
        $this->actingAs($this->user)->get('/profile')
            ->assertInertia(fn ($page) => $page->where('smsShortcut.active', true)->missing('smsShortcut.token'));

        $this->actingAs($this->user)->delete('/profile/sms-token')->assertRedirect();
        $this->assertSame(0, PersonalAccessToken::where('name', SmsShortcutService::TOKEN_NAME)->count());
    }

    public function test_the_webhook_is_throttled(): void
    {
        $token = $this->shortcutToken();

        for ($i = 0; $i < 30; $i++) {
            $this->postSms($token, self::sms($i));
        }

        $this->postSms($token, self::sms(99))->assertStatus(429);
    }

    // --- Revue de sécurité ---

    private function noted(int $amount): Transaction
    {
        $tx = new Transaction(['amount' => $amount, 'type' => 'expense', 'account_id' => $this->airtel->id, 'date' => now()->toDateString(), 'beneficiary' => 'note']);
        $tx->user_id = $this->user->id;
        $tx->save();

        return $tx;
    }

    public function test_an_sms_with_a_gap_never_creates_confirmed_fees(): void
    {
        $noted = $this->noted(15000); // SMS de 15 500 : écart de 500, des frais possibles

        $this->postSms($this->shortcutToken())->assertCreated();

        $this->assertNull($noted->fresh()->reconciled_at);
        $this->assertSame(0, Transaction::where('category_id', Category::BANK_FEES_ID)->count());
        $this->assertSame(1, Transaction::where('status', Transaction::STATUS_PENDING)->count());
    }

    public function test_an_sms_reference_never_blocks_the_real_statement_line(): void
    {
        $this->noted(15500);
        $this->postSms($this->shortcutToken())->assertOk();

        $this->assertSame('sms:PP261112.1400.A00001', StatementLine::sole()->external_ref);
        $entry = new \App\DTOs\StatementEntry(0, now()->toDateString(), -15500, 0, 'DIGITECH', 'PP261112.1400.A00001', 'ok', '');
        $row = app(\App\Services\StatementMatcher::class)->match($this->airtel, [$entry])['lines'][0];
        $this->assertSame('already_imported', $row['status']); // reconnue, pas dupliquée
    }

    public function test_an_sms_without_reference_sent_twice_is_pointed_once(): void
    {
        $this->noted(15500);
        $token = $this->shortcutToken();
        $sms = 'Paiement de 15500F DIGITECH pour achat. Nouveau solde 84500F.';

        $this->postSms($token, $sms)->assertOk();
        $this->travel(1)->days();
        $this->postSms($token, $sms)->assertOk(); // doublon reconnu

        $this->assertSame(0, Transaction::where('status', Transaction::STATUS_PENDING)->count());
    }

    public function test_the_restricted_token_gets_a_minimal_answer(): void
    {
        $this->noted(15500);

        $this->postSms($this->shortcutToken())->assertOk()->assertJsonMissingPath('transaction_id')->assertJsonMissingPath('result');
    }

    public function test_the_shortcut_token_does_not_expire_after_thirty_days(): void
    {
        $shortcut = $this->shortcutToken();
        $api = $this->user->createToken('api-token')->plainTextToken;
        $this->travel(40)->days();

        $this->postSms($shortcut)->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($api)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_changing_the_password_revokes_every_token(): void
    {
        $this->shortcutToken();
        $this->user->createToken('api-token');

        $this->actingAs($this->user)->put('/profile/password', [
            'current_password' => 'password', 'password' => 'N0uveau-mot-de-passe!', 'password_confirmation' => 'N0uveau-mot-de-passe!',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->user->tokens()->count());
    }
}

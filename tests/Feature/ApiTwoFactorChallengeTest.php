<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Non-régression P1 : le challenge 2FA de l'API doit compléter l'étape mot de passe,
 * pas la remplacer.
 */
class ApiTwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = (new Google2FA)->generateSecretKey();
    }

    private function userWithTwoFactor(): User
    {
        return User::factory()->create([
            'password' => Hash::make('secret-password'),
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-aaaa'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function validCode(): string
    {
        return (new Google2FA)->getCurrentOtp($this->secret);
    }

    private function loginToken(User $user): string
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->json('login_token');
    }

    public function test_login_returns_a_login_token_and_no_user_id(): void
    {
        $user = $this->userWithTwoFactor();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('user_id');
        $this->assertIsString($response->json('login_token'));
    }

    public function test_challenge_refuses_a_bare_user_id_without_password_step(): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/two-factor-challenge', [
            'user_id' => $user->id,
            'code' => $this->validCode(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_challenge_refuses_a_forged_login_token(): void
    {
        $this->userWithTwoFactor();

        $this->postJson('/api/two-factor-challenge', [
            'login_token' => 'not-a-valid-token',
            'code' => $this->validCode(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_challenge_refuses_an_expired_login_token(): void
    {
        $user = $this->userWithTwoFactor();
        $token = $this->loginToken($user);

        $this->travel(6)->minutes();

        $this->postJson('/api/two-factor-challenge', [
            'login_token' => $token,
            'code' => $this->validCode(),
        ])->assertStatus(422);
    }

    public function test_challenge_refuses_a_token_issued_before_a_password_change(): void
    {
        $user = $this->userWithTwoFactor();
        $token = $this->loginToken($user);

        $user->forceFill(['password' => Hash::make('another-password')])->save();

        $this->postJson('/api/two-factor-challenge', [
            'login_token' => $token,
            'code' => $this->validCode(),
        ])->assertStatus(422);
    }

    public function test_challenge_issues_an_api_token_with_login_token_and_valid_code(): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/two-factor-challenge', [
            'login_token' => $this->loginToken($user),
            'code' => $this->validCode(),
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_challenge_accepts_a_recovery_code_with_login_token(): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/two-factor-challenge', [
            'login_token' => $this->loginToken($user),
            'recovery_code' => 'recovery-aaaa',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function nonStringCodes(): array
    {
        return [
            'recovery_code true' => [['recovery_code' => true]],
            'recovery_code 1' => [['recovery_code' => 1]],
            'recovery_code array' => [['recovery_code' => ['recovery-aaaa']]],
            'code array' => [['code' => ['123456']]],
            'code true' => [['code' => true]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonStringCodes')]
    public function test_challenge_refuses_non_string_codes(array $payload): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/two-factor-challenge', ['login_token' => $this->loginToken($user)] + $payload)
            ->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_recovery_code_works_only_once(): void
    {
        $user = $this->userWithTwoFactor();
        $payload = fn () => ['login_token' => $this->loginToken($user), 'recovery_code' => 'recovery-aaaa'];

        $this->postJson('/api/two-factor-challenge', $payload())->assertOk();
        $this->postJson('/api/two-factor-challenge', $payload())->assertStatus(422);
    }

    public function test_challenge_refuses_a_wrong_code_even_with_login_token(): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/two-factor-challenge', [
            'login_token' => $this->loginToken($user),
            'code' => '000000',
        ])->assertStatus(422);
    }
}

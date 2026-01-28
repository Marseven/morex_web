<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorAuthController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return back()->withErrors(['2fa' => 'L\'authentification à deux facteurs est déjà activée.']);
        }

        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
        ])->save();

        return back()->with('two_factor_secret', $secret);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return back()->withErrors(['code' => 'Veuillez d\'abord activer l\'authentification à deux facteurs.']);
        }

        $secret = decrypt($user->two_factor_secret);

        if (!$this->google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'Le code est invalide. Veuillez réessayer.']);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
        ])->save();

        return back()->with('success', 'Authentification à deux facteurs activée avec succès.');
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Authentification à deux facteurs désactivée.');
    }

    public function showChallenge(): Response
    {
        if (!session('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request)
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $userId = session('login.id');
        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        // Try regular TOTP code first
        if ($request->filled('code')) {
            $secret = decrypt($user->two_factor_secret);

            if ($this->google2fa->verifyKey($secret, $request->code)) {
                return $this->completeTwoFactorLogin($request, $user);
            }

            return back()->withErrors(['code' => 'Le code est invalide.']);
        }

        // Try recovery code
        if ($request->filled('recovery_code')) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $recoveryCode = $request->recovery_code;

            if (in_array($recoveryCode, $recoveryCodes)) {
                // Remove used recovery code
                $recoveryCodes = array_values(array_diff($recoveryCodes, [$recoveryCode]));
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                ])->save();

                return $this->completeTwoFactorLogin($request, $user);
            }

            return back()->withErrors(['recovery_code' => 'Le code de récupération est invalide.']);
        }

        return back()->withErrors(['code' => 'Veuillez fournir un code.']);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (!$user->two_factor_confirmed_at) {
            return back()->withErrors(['2fa' => 'L\'authentification à deux facteurs n\'est pas activée.']);
        }

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
        ])->save();

        return back()->with('success', 'Codes de récupération régénérés avec succès.');
    }

    public function getQrCodeSvg(Request $request): string
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return '';
        }

        $secret = decrypt($user->two_factor_secret);

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($qrCodeUrl);
    }

    protected function completeTwoFactorLogin(Request $request, User $user)
    {
        Auth::login($user, session('login.remember', false));

        session()->forget(['login.id', 'login.remember']);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    protected function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn () => Str::upper(Str::random(4) . '-' . Str::random(4)))->all();
    }
}

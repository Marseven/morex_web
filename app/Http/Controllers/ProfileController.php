<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $twoFactorData = $this->getTwoFactorData($user);

        return Inertia::render('Profile/Edit', [
            'user' => $user->only('id', 'name', 'email', 'phone', 'avatar', 'theme'),
            'twoFactor' => $twoFactorData,
        ]);
    }

    protected function getTwoFactorData($user): array
    {
        $data = [
            'enabled' => $user->hasTwoFactorEnabled(),
            'qrCodeSvg' => null,
            'secret' => null,
            'recoveryCodes' => null,
        ];

        // If 2FA is being set up (secret exists but not confirmed)
        if ($user->two_factor_secret && !$user->two_factor_confirmed_at) {
            $google2fa = new Google2FA();
            $secret = decrypt($user->two_factor_secret);

            $qrCodeUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret
            );

            $renderer = new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);

            $data['qrCodeSvg'] = $writer->writeString($qrCodeUrl);
            $data['secret'] = $secret;
        }

        // If 2FA is enabled, show recovery codes
        if ($user->two_factor_confirmed_at && $user->two_factor_recovery_codes) {
            $data['recoveryCodes'] = json_decode(decrypt($user->two_factor_recovery_codes), true);
        }

        return $data;
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profil mis à jour avec succès.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Mot de passe mis à jour avec succès.');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('success', 'Photo de profil mise à jour.');
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return back()->with('success', 'Photo de profil supprimée.');
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:dark,light'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Thème mis à jour.');
    }
}

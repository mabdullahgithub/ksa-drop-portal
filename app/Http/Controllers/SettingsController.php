<?php

namespace App\Http\Controllers;

use App\Notifications\SettingsUpdatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class SettingsController extends Controller
{
    public function profile(Request $request): Response
    {
        return Inertia::render('Settings/Profile', [
            'user' => $request->user()->only('id', 'name', 'email', 'avatar', 'two_factor_enabled'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $user = $request->user();
        $changes = [];

        if ($user->name !== $validated['name']) {
            $changes['name'] = ['from' => $user->name, 'to' => $validated['name']];
        }

        if ($user->email !== $validated['email']) {
            $changes['email'] = ['from' => $user->email, 'to' => $validated['email']];
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (!empty($changes)) {
            $user->notify(new SettingsUpdatedNotification('profile', $changes));
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('success', 'Profile picture updated successfully.');
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return back()->with('success', 'Profile picture removed.');
    }

    public function security(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Security', $this->twoFactorProps($user));
    }

    public function portalSecurity(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Portal/Settings/Security', $this->twoFactorProps($user));
    }

    private function twoFactorProps($user): array
    {
        return [
            'user' => $user->only('id', 'name', 'email', 'avatar', 'two_factor_enabled', 'two_factor_confirmed_at'),
            'twoFactorQrCodeUrl' => $user->two_factor_secret && ! $user->two_factor_confirmed_at
                ? $this->generateQrCodeUrl($user)
                : null,
            'twoFactorSecret' => $user->two_factor_secret && ! $user->two_factor_confirmed_at
                ? decrypt($user->two_factor_secret)
                : null,
            'recoveryCodes' => $user->two_factor_enabled && session('two_factor_recovery_codes')
                ? session('two_factor_recovery_codes')
                : null,
        ];
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $user->notify(new SettingsUpdatedNotification('security', ['password' => 'Password changed']));

        return back()->with('success', 'Password updated successfully.');
    }

    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $google2fa = new Google2FA;
        $user = $request->user();

        $secret = $google2fa->generateSecretKey();

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', 'Scan the QR code with your authenticator app and confirm with a code.');
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $google2fa = new Google2FA;
        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not set up.'],
            ]);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt($recoveryCodes->toJson()),
        ]);

        session()->flash('two_factor_recovery_codes', $recoveryCodes->toArray());

        return back()->with('success', 'Two-factor authentication has been enabled. Save your recovery codes in a safe place.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }

    public function showRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_enabled || ! $user->two_factor_recovery_codes) {
            return response()->json(['codes' => []]);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return response()->json(['codes' => $codes]);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return back()->withErrors(['error' => 'Two-factor authentication is not enabled.']);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => encrypt($recoveryCodes->toJson()),
        ]);

        session()->flash('two_factor_recovery_codes', $recoveryCodes->toArray());

        return back()->with('success', 'New recovery codes have been generated.');
    }

    protected function generateQrCodeUrl($user): string
    {
        $google2fa = new Google2FA;
        $secret = decrypt($user->two_factor_secret);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return $qrCodeUrl;
    }

    protected function generateRecoveryCodes(): Collection
    {
        return collect(range(1, 8))->map(function () {
            return strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        });
    }

    public function notifications(Request $request): Response
    {
        $user = $request->user();
        $preference = $user->preference;

        return Inertia::render('Settings/Notifications', [
            'preference' => $preference ? $preference->only([
                'toast_position',
                'notification_type',
                'mobile_notifications',
                'communication_emails',
                'social_emails',
                'marketing_emails',
                'security_emails',
                'user_management_emails',
                'in_app_notifications',
            ]) : null,
        ]);
    }

    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'toast_position' => ['required', 'in:top-left,top-center,top-right,bottom-left,bottom-center,bottom-right'],
            'notification_type' => ['required', 'in:all,mentions,none'],
            'mobile_notifications' => ['boolean'],
            'in_app_notifications' => ['boolean'],
        ]);

        $user = $request->user();

        $user->preference()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        $user->notify(new SettingsUpdatedNotification('notification preferences', $validated));

        return back()->with('success', 'Notification preferences updated successfully.');
    }

    public function email(Request $request): Response
    {
        $user = $request->user();
        $preference = $user->preference;
        $isAdmin = $user->can('manage-email-settings');

        $data = [
            'preference' => $preference ? $preference->only([
                'communication_emails',
                'social_emails',
                'marketing_emails',
                'security_emails',
                'user_management_emails',
            ]) : null,
            'isAdmin' => $isAdmin,
        ];

        if ($isAdmin) {
            $settings = \App\Models\EmailSetting::getActive();

            if ($settings) {
                $settingsData = $settings->toArray();
                $settingsData['password_status'] = $settings->hasPassword() ? 'set' : 'not_set';
                unset($settingsData['password']);
            } else {
                // Default values when no settings exist
                $settingsData = [
                    'id' => null,
                    'is_active' => false,
                    'driver' => 'smtp',
                    'host' => '',
                    'port' => 587,
                    'username' => '',
                    'encryption' => 'tls',
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'password_status' => 'not_set',
                ];
            }

            $data['emailSettings'] = $settingsData;
        }

        return Inertia::render('Settings/Email', $data);
    }

    public function updateEmailPreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'communication_emails' => ['boolean'],
            'social_emails' => ['boolean'],
            'marketing_emails' => ['boolean'],
            'user_management_emails' => ['boolean'],
        ]);

        $user = $request->user();

        $user->preference()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return back()->with('success', 'Email preferences updated successfully.');
    }

    public function updateEmailSettings(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage-email-settings'), 403, 'Unauthorized access to email settings.');

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'driver' => ['required', 'in:smtp,sendmail,mailgun,ses,postmark,log'],
            'host' => ['nullable', 'required_if:driver,smtp', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:driver,smtp', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $settings = \App\Models\EmailSetting::getActive() ?? new \App\Models\EmailSetting();

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $settings->fill($validated);
        $settings->save();

        \Log::info('Email settings updated from user settings', [
            'user_id' => auth()->id(),
            'driver' => $validated['driver'],
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('success', 'Email settings updated successfully.');
    }
}

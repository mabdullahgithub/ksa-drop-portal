<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationController extends Controller
{
    public function create(): Response
    {
        if (! session('two_factor_user_id')) {
            return Inertia::render('Auth/Login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = session('two_factor_user_id');

        if (! $userId) {
            throw ValidationException::withMessages([
                'code' => ['Session expired. Please login again.'],
            ]);
        }

        $user = \App\Models\User::findOrFail($userId);

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        $google2fa = new Google2FA;
        $secret = decrypt($user->two_factor_secret);

        $valid = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            if (in_array($request->code, $recoveryCodes)) {
                $recoveryCodes = array_diff($recoveryCodes, [$request->code]);
                $user->update([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
                ]);
                $valid = true;
            }
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        session()->forget('two_factor_user_id');

        Auth::loginUsingId($userId);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}

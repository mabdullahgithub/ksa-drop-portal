<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Exceptions\ClientPortalBlocked;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * What happens once a person has proved who they are: clients are held to the
 * portal switch and their account status,
 * and everyone lands on the first page they may open.
 */
trait CompletesLogin
{
    protected function completeLogin(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('client')) {
            return redirect()->intended(route($user->defaultLandingRoute(), absolute: false));
        }

        if (ClientPortalBlocked::enabled()) {
            $this->logoutQuietly($request);

            throw new ClientPortalBlocked();
        }

        $client = $user->client;

        if (! $client || $client->status !== 'active') {
            $this->logoutQuietly($request);

            $message = match ($client?->status) {
                'suspended' => __('Your account has been suspended. Please contact support.'),
                'inactive' => __('Your account is inactive. Please contact support to reactivate it.'),
                'pending' => __('Your account is still under review. We will email you once it is approved.'),
                'rejected' => __('Your signup request was not approved. Please contact support.'),
                default => __('Your account is not active. Please contact support.'),
            };

            throw ValidationException::withMessages(['email' => $message]);
        }

        return redirect()->intended(route('portal.dashboard', absolute: false));
    }

    private function logoutQuietly(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}

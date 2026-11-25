<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesLogin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * "Continue with Google", on the signup and login pages.
 *
 * - A Google account already linked to a user signs that user in, through the
 *   same 2FA step and client status checks as a password login.
 * - A new email goes back to the signup form with the name and email filled
 *   in from Google (kept in the session, not trusted from the form), where the
 *   person adds their company details. RegisteredUserController makes the
 *   account and links it.
 * - An email that already has a password account is never linked from here:
 *   the person signs in with their password as before.
 */
class GoogleAuthController extends Controller
{
    use CompletesLogin;

    /** Session key holding the verified Google profile until signup finishes. */
    public const SESSION_KEY = 'google_signup';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->toLogin(__('Google sign-in was cancelled or failed. Please try again.'));
        }

        $email = Str::lower((string) $google->getEmail());

        if ($email === '' || ! ($google->user['email_verified'] ?? false)) {
            return $this->toLogin(__('Your Google account has no verified email address.'));
        }

        $linked = User::where('google_id', $google->getId())->first();

        if ($linked) {
            return $this->signIn($request, $linked);
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            return $this->toLogin(__('An account with this email already exists. Please sign in with your password.'));
        }

        $request->session()->put(self::SESSION_KEY, [
            'id' => $google->getId(),
            'name' => $google->getName() ?: Str::before($email, '@'),
            'email' => $email,
        ]);

        return redirect()->route('register');
    }

    /** Clears the Google profile so the signup form goes back to email + password. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('register');
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        if ($user->two_factor_enabled && $user->two_factor_secret) {
            session(['two_factor_user_id' => $user->id]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        try {
            return $this->completeLogin($request);
        } catch (ValidationException $e) {
            return $this->toLogin(collect($e->errors())->flatten()->first());
        }
    }

    private function toLogin(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}

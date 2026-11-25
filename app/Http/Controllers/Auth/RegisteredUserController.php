<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Notifications\ClientCreatedNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public merchant signup.
 *
 * Makes the same user + client pair an admin makes from the Clients page, but
 * the client starts as Client::STATUS_PENDING: the person cannot sign in until
 * an admin reviews the account (and sets its charges) and activates it. The
 * activation sends the usual "account status changed" email.
 *
 * Signing up with Google: GoogleAuthController puts the verified Google
 * profile in the session; the name and email come from there, not the form,
 * and no password is asked for.
 */
class RegisteredUserController extends Controller
{
    private const PENDING_MESSAGE = 'Thanks for signing up! Your account is under review. We will email you as soon as it is approved.';

    public function create(Request $request): Response
    {
        $google = $request->session()->get(GoogleAuthController::SESSION_KEY);

        return Inertia::render('Auth/Register', [
            'googleEnabled' => GoogleAuthController::enabled(),
            'google' => $google ? ['name' => $google['name'], 'email' => $google['email']] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a field people never see. Bots that fill it get the same
        // success screen, and nothing is created.
        if (filled($request->input('website'))) {
            return redirect()->route('login')->with('status', self::PENDING_MESSAGE);
        }

        $google = $request->session()->get(GoogleAuthController::SESSION_KEY);

        if ($google) {
            $request->merge(['email' => $google['email']]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'required|string|max:20',
            'city' => 'nullable|string|max:100',
            'client_type' => 'required|in:dropshipper,fulfilment',
            'password' => $google ? ['exclude'] : ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.unique' => "You can't create an account with this email.",
            'client_type.required' => 'Choose the service you need.',
            'client_type.in' => 'Choose the service you need.',
        ]);

        $client = DB::transaction(function () use ($validated, $google) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                // Google signups never use it, but the column is required.
                'password' => Hash::make($google ? Str::random(64) : $validated['password']),
                'google_id' => $google['id'] ?? null,
            ]);

            if ($google) {
                // Google has already verified this address.
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->assignRole('client');

            return Client::create([
                'user_id' => $user->id,
                'created_by' => null,
                'client_types' => [$validated['client_type']],
                'company_name' => $validated['company_name'],
                'short_id' => Client::generateClientId($validated['company_name']),
                'contact_person' => $validated['name'],
                'phone' => $validated['phone'],
                'city' => $validated['city'] ?? null,
                'country' => 'SA',
                'status' => Client::STATUS_PENDING,
                'portal_features' => ['orders', 'inventory', 'revenue'],
            ]);
        });

        $request->session()->forget(GoogleAuthController::SESSION_KEY);

        event(new Registered($client->user));

        // Tell the admins there is a signup waiting for review.
        try {
            User::role(['admin', 'superadmin'])->each(
                fn ($admin) => $admin->notify(new ClientCreatedNotification($client))
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('login')->with('status', self::PENDING_MESSAGE);
    }
}

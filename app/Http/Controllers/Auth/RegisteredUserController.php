<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\Signups\SignupReceivedMail;
use App\Models\Client;
use App\Models\User;
use App\Notifications\ClientCreatedNotification;
use App\Rules\NotGuessablePassword;
use App\Services\EmailService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
 */
class RegisteredUserController extends Controller
{
    private const SUBMITTED_KEY = 'signup_submitted';

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a field people never see. Bots that fill it get the same
        // success screen, and nothing is created.
        if (filled($request->input('website'))) {
            return $this->toSubmitted($request, (string) $request->string('email'), null);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'required|string|max:20',
            'city' => 'nullable|string|max:100',
            'client_type' => 'required|in:dropshipper,fulfilment',
            'password' => [
                'required',
                'confirmed',
                // Stricter than the app default. bcrypt ignores anything past 72 bytes.
                Rules\Password::min(12)->max(72)->mixedCase()->numbers()->symbols(),
                new NotGuessablePassword([$request->input('name'), $request->input('email')]),
            ],
        ], [
            'email.unique' => "You can't create an account with this email.",
            'client_type.required' => 'Choose the service you need.',
            'client_type.in' => 'Choose the service you need.',
        ]);

        $client = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole('client');

            $client = Client::create([
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

            // Not fillable: only signup and its review write these.
            $client->forceFill(['signed_up_at' => now()])->save();

            return $client;
        });

        event(new Registered($client->user));

        $this->sendReceivedMail($client);

        // Tell the admins there is a signup waiting for review.
        try {
            // By name, so a missing role does not stop the others being told.
            User::whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'superadmin']))->get()->each(
                fn ($admin) => $admin->notify(new ClientCreatedNotification($client))
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->toSubmitted($request, $client->user->email, $client->short_id);
    }

    /** "We received your request", to the applicant. The signup stands if it fails. */
    private function sendReceivedMail(Client $client): void
    {
        try {
            $emails = app(EmailService::class);

            if ($emails->isEnabled()) {
                $emails->configureMailer();
                Mail::to($client->user->email)->send(new SignupReceivedMail($client));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The confirmation screen. Held in the session so a refresh keeps it. */
    public function submitted(Request $request): Response|RedirectResponse
    {
        $submitted = $request->session()->get(self::SUBMITTED_KEY);

        return $submitted
            ? Inertia::render('Auth/RegisterSubmitted', $submitted)
            : redirect()->route('register');
    }

    private function toSubmitted(Request $request, string $email, ?string $reference): RedirectResponse
    {
        $request->session()->put(self::SUBMITTED_KEY, ['email' => $email, 'reference' => $reference]);

        return redirect()->route('register.submitted');
    }
}

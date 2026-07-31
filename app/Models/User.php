<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\Auth\ResetPasswordMail;
use App\Services\EmailService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'avatar', 'two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's preferences.
     */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Get the client profile associated with the user.
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /**
     * Resolve the best landing route for this user based on their permissions.
     *
     * Falls through an ordered list mirroring the sidebar navigation and returns
     * the first page the user can view. Used so a user without "view dashboard"
     * lands on a page they can actually access instead of hitting a 403.
     */
    public function defaultLandingRoute(): string
    {
        if ($this->hasRole('client')) {
            return 'portal.dashboard';
        }

        // permission => route name, in sidebar order.
        $candidates = [
            'view dashboard' => 'dashboard',
            'view client' => 'client',
            'view inventory' => 'inventory',
            'view orders' => 'orders',
            'view apps' => 'apps',
            'view tags' => 'tags',
            'view users' => 'team-management.users',
        ];

        foreach ($candidates as $permission => $route) {
            if ($this->can($permission)) {
                return $route;
            }
        }

        // Settings has no permission gate, so every authenticated user can reach it.
        return 'settings';
    }

    /**
     * Send the password reset notification using the branded mailable.
     */
    public function sendPasswordResetNotification($token): void
    {
        $emailService = app(EmailService::class);

        if (! $emailService->isEnabled()) {
            return;
        }

        $emailService->configureMailer();

        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = Config::get(
            'auth.passwords.'.Config::get('auth.defaults.passwords').'.expire',
            60
        );

        Mail::to($this->email)->send(
            new ResetPasswordMail($this, $resetUrl, $expireMinutes.' minutes')
        );
    }
}

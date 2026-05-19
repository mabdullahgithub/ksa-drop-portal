<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\User;
use Exception;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class EmailService
{
    /**
     * Check if email system is enabled and configured.
     */
    public function isEnabled(): bool
    {
        $settings = EmailSetting::getActive();

        return $settings && $settings->is_active;
    }

    /**
     * Configure mail settings dynamically.
     */
    public function configureMailer(?EmailSetting $settings = null): void
    {
        if (! $settings) {
            $settings = EmailSetting::getActive();
        }

        if (! $settings) {
            return;
        }

        $config = [
            'transport' => $settings->driver,
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            'password' => $settings->password,
            'encryption' => $settings->encryption === 'none' ? null : $settings->encryption,
            'from' => [
                'address' => $settings->from_address,
                'name' => $settings->from_name,
            ],
        ];

        Config::set('mail.mailers.smtp', $config);
        Config::set('mail.from.address', $settings->from_address);
        Config::set('mail.from.name', $settings->from_name);
    }

    /**
     * Send email with user preference check and rate limiting.
     */
    public function sendEmail(Mailable $mailable, User $user, string $emailType): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $this->canSendEmail($user, $emailType)) {
            return false;
        }

        // Rate limiting: 20 emails per user per hour
        $rateLimitKey = 'email:send:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            \Log::warning('Email rate limit exceeded', [
                'user_id' => $user->id,
                'email_type' => $emailType,
            ]);

            return false;
        }

        try {
            $this->configureMailer();

            $log = $this->createEmailLog($user, $mailable, $emailType);

            Mail::to($user->email)->send($mailable);

            $log->markAsSent();

            // Increment rate limiter only on successful send
            RateLimiter::hit($rateLimitKey, 3600);

            return true;
        } catch (Exception $e) {
            if (isset($log)) {
                $log->markAsFailed($e->getMessage());
            }

            report($e);

            return false;
        }
    }

    /**
     * Check if email can be sent based on user preferences.
     */
    public function canSendEmail(User $user, string $emailType): bool
    {
        $preference = $user->preference;

        if (!$preference) {
            // Default behavior if no preference exists
            return $this->isSecurityEmail($emailType);
        }

        // Check individual email preference first
        $individualPreferenceKey = $this->getIndividualPreferenceKey($emailType);
        if ($individualPreferenceKey && property_exists($preference, $individualPreferenceKey)) {
            // Security emails are always enabled
            if ($this->isSecurityEmail($emailType)) {
                return true;
            }
            return $preference->$individualPreferenceKey ?? false;
        }

        // Fallback to category-based preferences
        return match ($this->getEmailCategory($emailType)) {
            'communication' => $preference->communication_emails ?? false,
            'social' => $preference->social_emails ?? false,
            'marketing' => $preference->marketing_emails ?? false,
            'user_management' => $preference->user_management_emails ?? true,
            default => false,
        };
    }

    /**
     * Get individual preference key for email type.
     */
    protected function getIndividualPreferenceKey(string $emailType): ?string
    {
        $keyMap = [
            'verify_email' => 'verify_email',
            'reset_password' => 'reset_password',
            'password_changed' => 'password_changed',
            'two_factor_enabled' => 'two_factor_enabled',
            'two_factor_disabled' => 'two_factor_disabled',
            'welcome_user' => 'welcome_user',
            'user_created' => 'user_created_admin',
            'user_updated' => 'user_updated',
            'welcome' => 'welcome',
            'settings_updated' => 'settings_updated',
        ];

        return $keyMap[$emailType] ?? null;
    }

    /**
     * Test SMTP connection with rate limiting.
     */
    public function testConnection(EmailSetting $settings, ?int $userId = null): array
    {
        // Rate limiting: 5 test emails per user per hour
        $rateLimitKey = 'email:test:'.($userId ?? 'system');
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return [
                'success' => false,
                'message' => 'Too many test attempts. Please try again later.',
            ];
        }

        try {
            $this->configureMailer($settings);

            if (! $settings->test_email) {
                throw new Exception('Test email address is required');
            }

            Mail::raw('This is a test email from '.config('app.name'), function ($message) use ($settings) {
                $message->to($settings->test_email)
                    ->subject('Test Email - '.config('app.name'));
            });

            $settings->update([
                'test_status' => 'success',
                'test_error' => null,
                'last_tested_at' => now(),
            ]);

            // Increment rate limiter only on successful test
            RateLimiter::hit($rateLimitKey, 3600);

            return ['success' => true, 'message' => 'Test email sent successfully!'];
        } catch (Exception $e) {
            $settings->update([
                'test_status' => 'failed',
                'test_error' => $e->getMessage(),
                'last_tested_at' => now(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create email log entry.
     */
    protected function createEmailLog(User $user, Mailable $mailable, string $emailType): EmailLog
    {
        return EmailLog::create([
            'user_id' => $user->id,
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'subject' => $mailable->subject ?? 'No Subject',
            'email_type' => $emailType,
            'status' => 'queued',
        ]);
    }

    /**
     * Check if email type is security-related.
     */
    protected function isSecurityEmail(string $emailType): bool
    {
        $securityTypes = [
            'verify_email',
            'reset_password',
            'password_changed',
            'two_factor_enabled',
            'two_factor_disabled',
            'login_new_device',
            'security_alert',
        ];

        return in_array($emailType, $securityTypes);
    }

    /**
     * Get email category for preference checking.
     */
    protected function getEmailCategory(string $emailType): string
    {
        $categories = [
            'welcome' => 'communication',
            'user_created' => 'user_management',
            'user_updated' => 'user_management',
            'settings_updated' => 'communication',
            'team_invitation' => 'communication',
            'mention' => 'social',
            'newsletter' => 'marketing',
            'product_update' => 'marketing',
        ];

        return $categories[$emailType] ?? 'communication';
    }
}

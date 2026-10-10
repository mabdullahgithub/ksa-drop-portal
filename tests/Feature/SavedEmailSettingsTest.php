<?php

namespace Tests\Feature;

use App\Models\EmailSetting;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every email leaves with the settings saved in the portal, whichever code
 * sends it: the .env MAIL_* values are only what is used when none are saved.
 */
class SavedEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // What the server's .env holds: an old password.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'env.example.com', 'port' => 465, 'username' => 'env-user', 'password' => 'old-password'],
        ]);
    }

    private function saveSettings(array $attributes = []): EmailSetting
    {
        return EmailSetting::create($attributes + [
            'is_active' => true,
            'driver' => 'smtp',
            'host' => 'saved.example.com',
            'port' => 465,
            'username' => 'saved-user',
            'password' => 'new-password',
            'encryption' => 'ssl',
            'from_address' => 'portal@example.com',
            'from_name' => 'Portal',
        ]);
    }

    public function test_a_mailer_nobody_configured_uses_the_saved_settings(): void
    {
        $this->saveSettings();

        // The way a notification gets its mailer: no EmailService call first.
        $transport = (string) Mail::mailer()->getSymfonyTransport();

        $this->assertStringContainsString('saved.example.com', $transport);
        $this->assertSame('new-password', config('mail.mailers.smtp.password'));
        $this->assertSame('portal@example.com', config('mail.from.address'));
    }

    public function test_with_nothing_saved_the_env_settings_stand(): void
    {
        $this->assertStringContainsString('env.example.com', (string) Mail::mailer()->getSymfonyTransport());
        $this->assertSame('old-password', config('mail.mailers.smtp.password'));
    }

    public function test_settings_changed_after_a_mailer_was_made_reach_the_next_send(): void
    {
        $settings = $this->saveSettings();
        Mail::mailer();

        $settings->update(['host' => 'moved.example.com']);
        app(EmailService::class)->configureMailer($settings);

        $this->assertStringContainsString('moved.example.com', (string) Mail::mailer()->getSymfonyTransport());
    }
}

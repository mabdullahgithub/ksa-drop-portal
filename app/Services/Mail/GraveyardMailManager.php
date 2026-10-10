<?php

namespace App\Services\Mail;

use App\Services\EmailService;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;

/**
 * The framework's mail manager, with every mailer's transport wrapped so a
 * recipient the server refuses as unknown ends up in the email graveyard.
 *
 * It also puts the email settings saved in the portal in place before the
 * first mailer is made. Every send passes through here, so a notification
 * leaves with the same server and password as the Settings page's test email,
 * rather than with whatever MAIL_* values the .env still holds.
 */
class GraveyardMailManager extends MailManager
{
    private bool $savedSettingsApplied = false;

    public function mailer($name = null)
    {
        if (! $this->savedSettingsApplied) {
            $this->savedSettingsApplied = true;

            try {
                $this->app->make(EmailService::class)->configureMailer();
            } catch (\Throwable $e) {
                // No settings to read (say, no table yet): the .env values stand.
                Log::channel('mail')->warning('Could not apply the saved email settings', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return parent::mailer($name);
    }

    public function build($config)
    {
        $mailer = parent::build($config);

        $mailer->setSymfonyTransport(new RejectedRecipientTransport($mailer->getSymfonyTransport()));

        return $mailer;
    }
}

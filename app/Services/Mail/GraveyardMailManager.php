<?php

namespace App\Services\Mail;

use Illuminate\Mail\MailManager;

/**
 * The framework's mail manager, with every mailer's transport wrapped so a
 * recipient the server refuses as unknown ends up in the email graveyard.
 */
class GraveyardMailManager extends MailManager
{
    public function build($config)
    {
        $mailer = parent::build($config);

        $mailer->setSymfonyTransport(new RejectedRecipientTransport($mailer->getSymfonyTransport()));

        return $mailer;
    }
}

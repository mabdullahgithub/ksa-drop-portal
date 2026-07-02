<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;

abstract class BaseMailable extends Mailable
{
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailer'            => config('app.name'),
                'List-Unsubscribe'    => '<' . rtrim(config('app.url'), '/') . '/settings/email>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }
}

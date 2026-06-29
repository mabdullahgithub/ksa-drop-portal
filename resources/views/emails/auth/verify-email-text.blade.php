{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $user->name }},

Thank you for signing up with {{ config('app.name') }}! To complete your registration, please verify your email address by visiting the link below.

Verify your email:
{{ $verificationUrl }}

This link will expire in {{ $expirationTime ?? '60 minutes' }}.

If you did not create an account with {{ config('app.name') }}, you can safely ignore this email.

---
This email was sent to {{ $recipient ?? 'you' }} because an account was created with this address.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

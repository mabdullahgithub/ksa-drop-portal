{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $user->name ?? 'there' }},

We received a request to reset the password for your {{ config('app.name') }} account. Visit the link below to set a new password.

Reset your password:
{{ $resetUrl }}

This link will expire in {{ $expirationTime ?? '60 minutes' }}.

If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.

---
This email was sent to {{ $recipient ?? 'you' }} because a password reset was requested for this address.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

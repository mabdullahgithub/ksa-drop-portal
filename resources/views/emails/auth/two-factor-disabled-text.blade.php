{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $user->name }},

Two-factor authentication (2FA) has been disabled on your {{ config('app.name') }} account.

Details:
  Date: {{ now()->format('F j, Y') }}
  Time: {{ now()->format('g:i A T') }}

Your account no longer requires a verification code when signing in. We recommend re-enabling 2FA to keep your account protected.

Re-enable two-factor authentication:
{{ rtrim(config('app.url'), '/') }}/settings/security

If you did not disable two-factor authentication, please re-enable it and update your password immediately.

---
This email was sent to {{ $recipient ?? 'you' }} because your account security settings changed.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

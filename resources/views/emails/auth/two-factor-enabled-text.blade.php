{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $user->name }},

Two-factor authentication (2FA) has been enabled on your {{ config('app.name') }} account. From now on you will need your authenticator app when signing in.

Details:
  Enabled on: {{ now()->format('F j, Y \a\t g:i A') }}

What this means:
  - You will need your authenticator app to sign in
  - Your account has an extra layer of protection
  - Recovery codes have been generated for emergency access

Keep your recovery codes in a safe place in case you lose access to your authenticator app.

View your security settings:
{{ rtrim(config('app.url'), '/') }}/settings/security

If you did not enable two-factor authentication, please reset your password and contact our support team immediately.

---
This email was sent to {{ $recipient ?? 'you' }} because your account security settings changed.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

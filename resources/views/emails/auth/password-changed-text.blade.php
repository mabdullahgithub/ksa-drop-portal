{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $user->name }},

Your {{ config('app.name') }} account password was changed on {{ now()->format('F j, Y \a\t g:i A') }}.

Details:
  Date: {{ now()->format('F j, Y') }}
  Time: {{ now()->format('g:i A T') }}
@if(isset($ipAddress))
  IP Address: {{ $ipAddress }}
@endif

If you made this change, no further action is needed.

If you did not change your password, please reset it immediately and contact our support team.

Review your security settings:
{{ rtrim(config('app.url'), '/') }}/settings/security

---
This email was sent to {{ $recipient ?? 'you' }} because your account password was changed.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

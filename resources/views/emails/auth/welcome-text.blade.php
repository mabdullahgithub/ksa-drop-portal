Welcome to {{ config('app.name') }}
{{ str_repeat('=', strlen('Welcome to ' . config('app.name'))) }}

Hi {{ $user->name }},

Your account has been created and is ready to use.

Account details:
  Email:      {{ $user->email }}
  Registered: {{ now()->format('F j, Y') }}

Here are some things you can do to get started:
  - Complete your profile information
  - Explore the dashboard and features
  - Set up two-factor authentication for extra security
  - Customize your notification preferences

Go to your dashboard:
{{ rtrim(config('app.url'), '/') }}/dashboard

If you have questions or need help, visit our Help Center:
{{ rtrim(config('app.url'), '/') }}/help-center

---
This email was sent to {{ $recipient ?? 'you' }} because an account was created with this address.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

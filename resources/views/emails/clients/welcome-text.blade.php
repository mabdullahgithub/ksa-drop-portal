Welcome to {{ config('app.name') }}

Hi {{ $client->contact_person ?? $client->user->name }},

Your client portal account has been created for {{ $client->company_name }}. You can now access your portal to track orders, inventory, and revenue.

Sign-in details:
  Email:              {{ $client->user->email }}
  Initial access code: {{ $password }}

For your security, please set a personal password after your first sign-in.

Sign in to Portal:
{{ $loginUrl }}

Once logged in, you can:
  - View and track your orders
  - Monitor your inventory
  - Check your revenue and earnings
  - Update your company profile
  - Enable two-factor authentication for extra security

---
This email was sent because an account was created for your email address.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

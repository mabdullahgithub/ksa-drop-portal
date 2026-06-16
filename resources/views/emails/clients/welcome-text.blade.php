Welcome to {{ config('app.name') }}!

Hi {{ $client->contact_person ?? $client->user->name }},

Your client portal account has been created for {{ $client->company_name }}. You can now access your portal to track orders, inventory, and revenue.

Here are your sign-in details for the portal:
  Email:              {{ $client->user->email }}
  Temporary password: {{ $password }}

For your security, we recommend updating this temporary password to one of your own after your first sign-in.

Login to Portal:
{{ $loginUrl }}

Once logged in, you can:
  - View and track your orders
  - Monitor your inventory
  - Check your revenue and earnings
  - Update your company profile
  - Enable two-factor authentication for extra security

Best regards,
The {{ config('app.name') }} Team

---
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
This email was sent because an account was created for your email address.

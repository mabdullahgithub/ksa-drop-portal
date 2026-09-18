Your Shopify store is connected

Hi {{ $client->contact_person ?? $client->user->name }},

Great news — {{ $shopDomain }} is now connected to your {{ config('app.name') }} portal account for {{ $client->company_name }}. You're all set, and we're ready to start fulfilling your orders.

Connection details:
  Shopify store:  {{ $shopDomain }}
  Portal account: {{ $client->company_name }}
  Connected on:   {{ now()->format('d M Y, H:i') }}

View Connection:
{{ $connectorsUrl }}

What happens now:
  - New Shopify orders sync to your portal automatically
  - Items stocked at the {{ config('app.name') }} location are routed to us for fulfillment
  - Tracking details are sent back to Shopify as soon as your orders ship
  - You can adjust sync settings any time from Connectors in your portal

Orders placed before the connection stay in Shopify and are not imported.

If you didn't make this change, or have any questions, just reply to this email.

Best regards,
The {{ config('app.name') }} Team

---
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

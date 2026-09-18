Your Shopify store has been disconnected

Hi {{ $client->contact_person ?? $client->user->name }},

{{ $shopDomain }} is no longer connected to your {{ config('app.name') }} portal account for {{ $client->company_name }}.

Details:
  Shopify store:    {{ $shopDomain }}
@if($reason === 'uninstalled')
  Reason:           The {{ config('app.name') }} app was uninstalled from your Shopify admin
@else
  Reason:           The store was disconnected from your {{ config('app.name') }} portal
@endif
  Disconnected on:  {{ now()->format('d M Y, H:i') }}

New Shopify orders will no longer sync to your portal, and we can't receive fulfillment requests or send tracking details back to Shopify. Orders already in your portal are kept and stay visible there.

@if($reason === 'uninstalled')
To reconnect, reinstall {{ config('app.name') }} from the Shopify App Store, then click "Connect your store" in the app.
@else
To reconnect, open the {{ config('app.name') }} app in your Shopify admin and click "Connect your store".
@endif

View Connectors:
{{ $connectorsUrl }}

If you didn't make this change, or have any questions, just reply to this email.

Best regards,
The {{ config('app.name') }} Team

---
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

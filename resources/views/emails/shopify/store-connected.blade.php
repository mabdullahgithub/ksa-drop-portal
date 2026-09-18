@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Your Shopify store is connected</h1>

    <p class="email-text">
        Hi {{ $client->contact_person ?? $client->user->name }},
    </p>

    <p class="email-text">
        Great news — <strong>{{ $shopDomain }}</strong> is now connected to your {{ config('app.name') }} portal account for <strong>{{ $client->company_name }}</strong>. You're all set, and we're ready to start fulfilling your orders.
    </p>

    @component('emails.components.alert', ['type' => 'success'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Shopify store</strong><br>
            {{ $shopDomain }}
            <br><br>
            <strong style="color: #111827;">Portal account</strong><br>
            {{ $client->company_name }}
            <br><br>
            <strong style="color: #111827;">Connected on</strong><br>
            {{ now()->format('d M Y, H:i') }}
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => $connectorsUrl])
        View Connection
    @endcomponent

    <hr class="email-divider">

    <p class="email-text">
        What happens now:
    </p>

    <ul style="color: #4b5563; margin: 16px 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;">New Shopify orders sync to your portal automatically</li>
        <li style="margin-bottom: 8px;">Items stocked at the {{ config('app.name') }} location are routed to us for fulfillment</li>
        <li style="margin-bottom: 8px;">Tracking details are sent back to Shopify as soon as your orders ship</li>
        <li style="margin-bottom: 8px;">You can adjust sync settings any time from Connectors in your portal</li>
    </ul>

    <p class="email-text" style="color: #6b7280; font-size: 14px;">
        Orders placed before the connection stay in Shopify and are not imported.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        If you didn't make this change, or have any questions, just reply to this email.<br><br>
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

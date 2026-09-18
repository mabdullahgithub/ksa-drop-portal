@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Your Shopify store has been disconnected</h1>

    <p class="email-text">
        Hi {{ $client->contact_person ?? $client->user->name }},
    </p>

    <p class="email-text">
        <strong>{{ $shopDomain }}</strong> is no longer connected to your {{ config('app.name') }} portal account for <strong>{{ $client->company_name }}</strong>.
    </p>

    @component('emails.components.alert', ['type' => 'warning'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Shopify store</strong><br>
            {{ $shopDomain }}
            <br><br>
            <strong style="color: #111827;">Reason</strong><br>
            @if($reason === 'uninstalled')
                The {{ config('app.name') }} app was uninstalled from your Shopify admin
            @else
                The store was disconnected from your {{ config('app.name') }} portal
            @endif
            <br><br>
            <strong style="color: #111827;">Disconnected on</strong><br>
            {{ now()->format('d M Y, H:i') }}
        </p>
    @endcomponent

    <p class="email-text">
        New Shopify orders will no longer sync to your portal, and we can't receive fulfillment requests or send tracking details back to Shopify. Orders already in your portal are kept and stay visible there.
    </p>

    <p class="email-text">
        To reconnect,
        @if($reason === 'uninstalled')
            reinstall {{ config('app.name') }} from the Shopify App Store, then click <strong>Connect your store</strong> in the app.
        @else
            open the {{ config('app.name') }} app in your Shopify admin and click <strong>Connect your store</strong>.
        @endif
    </p>

    @component('emails.components.button', ['url' => $connectorsUrl])
        View Connectors
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        If you didn't make this change, or have any questions, just reply to this email.<br><br>
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

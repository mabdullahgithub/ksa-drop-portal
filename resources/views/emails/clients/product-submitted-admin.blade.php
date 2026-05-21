@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Product Awaiting Verification</h1>

    <p class="email-text">
        A fulfilment client has submitted a new product that requires your verification.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151;">
            <strong>Product Details:</strong><br>
            Product Name: {{ $product->name }}<br>
            Product Code: {{ $product->product_code }}<br>
            @if($product->sku)
            SKU: {{ $product->sku }}<br>
            @endif
            Quantity: {{ $product->quantity }}<br>
            @if($product->unit_price)
            Unit Price: SAR {{ number_format((float) $product->unit_price, 2) }}<br>
            @endif
            Submitted by: {{ $client->company_name }} ({{ $client->client_id }})
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => $reviewUrl])
        Review &amp; Verify Product
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

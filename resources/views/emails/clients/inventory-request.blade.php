@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">{{ $headline }}</h1>

    <p class="email-text">
        {{ $intro }}
    </p>

    @component('emails.components.alert', ['type' => $alert])
        <p style="margin: 0; color: #374151;">
            <strong>Product:</strong><br>
            Product Name: {{ $product?->name }}<br>
            Product Code: {{ $product?->product_code }}<br>
            @if($product?->sku)
            SKU: {{ $product->sku }}<br>
            @endif
            @if($client)
            Client: {{ $client->company_name }}
            @endif
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => $buttonUrl])
        {{ $buttonLabel }}
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

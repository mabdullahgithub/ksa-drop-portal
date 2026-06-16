@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Your Product Has Been Verified</h1>

    <p class="email-text">
        Your product has been reviewed and verified by our team, and is now active in your inventory.
    </p>

    @component('emails.components.alert', ['type' => 'success'])
        <p style="margin: 0; color: #374151;">
            <strong>Verified Product:</strong><br>
            Product Name: {{ $product->name }}<br>
            Product Code: {{ $product->product_code }}<br>
            @if($product->sku)
            SKU: {{ $product->sku }}<br>
            @endif
            Verified on: {{ $product->verified_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i') }}
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => $inventoryUrl])
        View My Inventory
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

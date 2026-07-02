{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

A client has submitted a new product that requires your review.

Product details:
  Name:          {{ $product->name }}
  Product Code:  {{ $product->product_code }}
@if($product->sku)
  SKU:           {{ $product->sku }}
@endif
  Quantity:      {{ $product->quantity }}
@if($product->unit_price)
  Unit Price:    SAR {{ number_format((float) $product->unit_price, 2) }}
@endif
  Submitted by:  {{ $client->company_name }} ({{ $client->client_id }})

Review and verify:
{{ $reviewUrl }}

---
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

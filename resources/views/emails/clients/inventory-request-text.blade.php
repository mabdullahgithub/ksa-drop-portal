{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

{{ $headline }}

{{ $intro }}

Product:
  Name:  {{ $product?->name }}
  Code:  {{ $product?->product_code }}
@if($product?->sku)
  SKU:   {{ $product->sku }}
@endif
@if($client)
  Client: {{ $client->company_name }}
@endif

{{ $buttonLabel }}:
{{ $buttonUrl }}

---
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

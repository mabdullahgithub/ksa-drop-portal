{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Your product has been reviewed and approved by our team. It is now active in your inventory.

Approved product:
  Name:        {{ $product->name }}
  Code:        {{ $product->product_code }}
@if($product->sku)
  SKU:         {{ $product->sku }}
@endif
  Approved on: {{ $product->verified_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i') }}

View your inventory:
{{ $inventoryUrl }}

---
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

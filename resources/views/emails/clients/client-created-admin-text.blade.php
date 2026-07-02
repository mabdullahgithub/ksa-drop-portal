{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

A new client has been registered and is ready to be managed.

Client details:
  Company:  {{ $client->company_name }}
  ID:       {{ $client->client_id }}
  Type:     {{ $client->type_label }}
@if($client->contact_person)
  Contact:  {{ $client->contact_person }}
@endif
@if($client->phone)
  Phone:    {{ $client->phone }}
@endif
  Email:    {{ $client->user->email ?? 'N/A' }}

View client profile:
{{ $detailUrl }}

---
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {{ $client->contact_person ?? $client->user->name }},

Your {{ config('app.name') }} account status for {{ $client->company_name }} has been updated.

@php
    $statusLabels = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];
    $statusMessages = [
        'active'    => 'Your account is now fully active. You can log in and use all portal features.',
        'inactive'  => 'Your account has been deactivated. Contact support if you have questions.',
        'suspended' => 'Your account has been suspended. Contact our support team to resolve this.',
    ];
@endphp
Status changed: {{ ucfirst($statusLabels[$oldStatus] ?? $oldStatus) }} → {{ ucfirst($statusLabels[$newStatus] ?? $newStatus) }}

{{ $statusMessages[$newStatus] ?? "Your account status is now {$newStatus}." }}

@if($newStatus === 'active')
Go to portal:
{{ $portalUrl }}

@endif
If you believe this was done in error, please contact our support team.

---
This email was sent to {{ $client->user->email ?? 'you' }} because your account status changed.
Manage email preferences: {{ rtrim(config('app.url'), '/') }}/settings/email
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

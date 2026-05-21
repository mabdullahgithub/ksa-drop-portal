@extends('emails.layouts.default')

@php
    $statusLabels = [
        'active'    => 'Active',
        'inactive'  => 'Inactive',
        'suspended' => 'Suspended',
    ];
    $statusMessages = [
        'active'    => 'Your account is now fully active. You can log in and use all portal features available to you.',
        'inactive'  => 'Your account has been deactivated. You will not be able to access the portal until it is reactivated. Please contact support if you have questions.',
        'suspended' => 'Your account has been suspended. Access to the portal has been blocked. Please contact our support team to resolve this.',
    ];
    $alertTypes = [
        'active'    => 'success',
        'inactive'  => 'info',
        'suspended' => 'warning',
    ];
@endphp

@section('content')
    <h1 class="email-title">Account Status Updated</h1>

    <p class="email-text">
        Hi {{ $client->contact_person ?? $client->user->name }},
    </p>

    <p class="email-text">
        Your account status for <strong>{{ $client->company_name }}</strong> has been updated.
    </p>

    @component('emails.components.alert', ['type' => $alertTypes[$newStatus] ?? 'info'])
        <p style="margin: 0; color: #374151;">
            <strong>Status changed:</strong>
            {{ ucfirst($statusLabels[$oldStatus] ?? $oldStatus) }} → {{ ucfirst($statusLabels[$newStatus] ?? $newStatus) }}<br><br>
            {{ $statusMessages[$newStatus] ?? "Your account status is now {$newStatus}." }}
        </p>
    @endcomponent

    @if($newStatus === 'active')
        @component('emails.components.button', ['url' => $portalUrl])
            Go to Portal
        @endcomponent
    @endif

    <p class="email-text" style="margin-top: 32px;">
        If you believe this was done in error, please contact our support team.<br><br>
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

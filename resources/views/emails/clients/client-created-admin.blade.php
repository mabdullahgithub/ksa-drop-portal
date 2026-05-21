@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">New Client Registered</h1>

    <p class="email-text">
        A new client has been registered on <strong>{{ config('app.name') }}</strong> and is ready to be managed.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151;">
            <strong>Client Details:</strong><br>
            Company: {{ $client->company_name }}<br>
            Client ID: {{ $client->client_id }}<br>
            Type: {{ $client->type_label }}<br>
            @if($client->contact_person)
            Contact: {{ $client->contact_person }}<br>
            @endif
            @if($client->phone)
            Phone: {{ $client->phone }}<br>
            @endif
            Email: {{ $client->user->email ?? '—' }}
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => $detailUrl])
        View Client Profile
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

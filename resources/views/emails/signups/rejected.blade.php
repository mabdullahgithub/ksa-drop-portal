@extends('emails.layouts.default', ['preheader' => 'We have reviewed your signup request.'])

@section('content')
    <h1 class="email-title">Update on your signup request</h1>

    <p class="email-text">
        Hi {{ $name }},
    </p>

    <p class="email-text">
        Thank you for your interest in <strong>{{ config('app.name') }}</strong>. We have reviewed the signup request for <strong>{{ $client->company_name }}</strong>, and we are unable to approve it at this time.
    </p>

    @if($client->signup_rejection_reason)
    @component('emails.components.alert', ['type' => 'warning'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Reason</strong><br>
            {{ $client->signup_rejection_reason }}
        </p>
    @endcomponent
    @endif

    <p class="email-text">
        If you think this is a mistake or can share more information, reply to this email and quote your reference <strong>{{ $client->client_id }}</strong>.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

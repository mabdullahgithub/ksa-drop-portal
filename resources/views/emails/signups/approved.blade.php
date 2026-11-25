@extends('emails.layouts.default', ['preheader' => 'You can now sign in to your merchant account.'])

@section('content')
    <h1 class="email-title">Your account is approved</h1>

    <p class="email-text">
        Hi {{ $name }},
    </p>

    <p class="email-text">
        Good news: your signup request for <strong>{{ $client->company_name }}</strong> has been approved. Welcome to {{ config('app.name') }}.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Reference:</strong> {{ $client->client_id }}<br>
            <strong style="color: #111827;">Business:</strong> {{ $client->company_name }}<br>
            <strong style="color: #111827;">Service:</strong> {{ $client->is_fulfilment ? 'Fulfilment' : 'Dropshipping' }}<br>
            <strong style="color: #111827;">Email:</strong> {{ $recipient }}
        </p>
    @endcomponent

    <p class="email-text">
        Sign in with your email address and the password you chose when you signed up.
    </p>

    @component('emails.components.button', ['url' => $loginUrl])
        Sign in to your account
    @endcomponent

    <p class="email-text">
        If you have forgotten your password, choose "Forgot Password" on the sign-in page.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

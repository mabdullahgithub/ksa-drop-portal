@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Welcome to {{ config('app.name') }}!</h1>

    <p class="email-text">
        Hi {{ $client->contact_person ?? $client->user->name }},
    </p>

    <p class="email-text">
        Your client portal account has been created for <strong>{{ $client->company_name }}</strong>. You can now access your portal to track orders, inventory, and revenue.
    </p>

    <p class="email-text">
        Here are your sign-in details for the portal:
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Email</strong><br>
            {{ $client->user->email }}
            <br><br>
            <strong style="color: #111827;">Temporary password</strong><br>
            <span style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; letter-spacing: 0.5px;">{{ $password }}</span>
        </p>
    @endcomponent

    <p class="email-text" style="color: #6b7280; font-size: 14px;">
        For your security, we recommend updating this temporary password to one of your own after your first sign-in.
    </p>

    @component('emails.components.button', ['url' => $loginUrl])
        Login to Portal
    @endcomponent

    <hr class="email-divider">

    <p class="email-text">
        Once logged in, you can:
    </p>

    <ul style="color: #4b5563; margin: 16px 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;">View and track your orders</li>
        <li style="margin-bottom: 8px;">Monitor your inventory</li>
        <li style="margin-bottom: 8px;">Check your revenue and earnings</li>
        <li style="margin-bottom: 8px;">Update your company profile</li>
        <li style="margin-bottom: 8px;">Enable two-factor authentication for extra security</li>
    </ul>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

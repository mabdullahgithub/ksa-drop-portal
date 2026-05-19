@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Verify Your Email Address</h1>

    <p class="email-text">
        Hi {{ $user->name }},
    </p>

    <p class="email-text">
        Thank you for signing up with {{ config('app.name') }}! To complete your registration and start using your account, please verify your email address.
    </p>

    @component('emails.components.button', ['url' => $verificationUrl])
        Verify Email Address
    @endcomponent

    @component('emails.components.alert', ['type' => 'warning'])
        <p style="margin: 0; color: #92400e;">
            <strong>Security Notice:</strong> This verification link will expire in {{ $expirationTime ?? '60 minutes' }}.
        </p>
    @endcomponent

    <p class="email-text">
        If the button above doesn't work, copy and paste this link into your browser:
    </p>

    <p style="word-break: break-all; color: #667eea; font-size: 14px; background-color: #f3f4f6; padding: 12px; border-radius: 4px;">
        {{ $verificationUrl }}
    </p>

    <hr class="email-divider">

    <p class="email-text text-muted">
        If you didn't create an account with {{ config('app.name') }}, you can safely ignore this email.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Reset Your Password</h1>

    <p class="email-text">
        Hi {{ $user->name ?? 'there' }},
    </p>

    <p class="email-text">
        You're receiving this email because we received a password reset request for your account.
    </p>

    @component('emails.components.button', ['url' => $resetUrl])
        Reset Password
    @endcomponent

    @component('emails.components.alert', ['type' => 'warning'])
        <p style="margin: 0; color: #92400e;">
            <strong>Security Notice:</strong> This password reset link will expire in {{ $expirationTime ?? '60 minutes' }}.
        </p>
    @endcomponent

    <p class="email-text">
        If the button above doesn't work, copy and paste this link into your browser:
    </p>

    <p style="word-break: break-all; color: #667eea; font-size: 14px; background-color: #f3f4f6; padding: 12px; border-radius: 4px;">
        {{ $resetUrl }}
    </p>

    <hr class="email-divider">

    @component('emails.components.alert', ['type' => 'danger'])
        <p style="margin: 0; color: #991b1b;">
            <strong>Didn't request this?</strong><br>
            If you didn't request a password reset, please ignore this email and your password will remain unchanged. Your account security is important to us.
        </p>
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

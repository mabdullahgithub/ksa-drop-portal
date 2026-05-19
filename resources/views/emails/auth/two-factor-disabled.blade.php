@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Two-Factor Authentication Disabled</h1>

    <p class="email-text">
        Hi {{ $user->name }},
    </p>

    <p class="email-text">
        This email confirms that two-factor authentication (2FA) has been disabled on your account.
    </p>

    @component('emails.components.alert', ['type' => 'warning'])
        <p style="margin: 0; color: #92400e;">
            <strong>Security Notice:</strong> Your account now has reduced security protection. We recommend re-enabling 2FA to keep your account safe.
        </p>
    @endcomponent

    <p class="email-text">
        <strong>Details:</strong>
    </p>

    <ul style="color: #4b5563; margin: 16px 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;">Date: {{ now()->format('F j, Y') }}</li>
        <li style="margin-bottom: 8px;">Time: {{ now()->format('g:i A T') }}</li>
    </ul>

    <hr class="email-divider">

    @component('emails.components.alert', ['type' => 'danger'])
        <p style="margin: 0; color: #991b1b;">
            <strong>Didn't disable this?</strong><br>
            If you didn't disable two-factor authentication, your account may be compromised. Please enable 2FA immediately and change your password.
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => config('app.url') . '/settings/security'])
        Re-enable Two-Factor Authentication
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

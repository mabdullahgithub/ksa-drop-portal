@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Two-Factor Authentication Enabled</h1>

    <p class="email-text">
        Hi {{ $user->name }},
    </p>

    <p class="email-text">
        Two-factor authentication (2FA) has been enabled on your account.
    </p>

    @component('emails.components.alert', ['type' => 'success'])
        <p style="margin: 0; color: #065f46;">
            <strong>✓ Your account is now more secure</strong><br>
            From now on, you'll need to provide a verification code from your authenticator app when signing in.
        </p>
    @endcomponent

    <p class="email-text">
        <strong>What this means:</strong>
    </p>

    <ul style="color: #4b5563; margin: 16px 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;">You'll need your authenticator app to sign in</li>
        <li style="margin-bottom: 8px;">Your account has an extra layer of protection</li>
        <li style="margin-bottom: 8px;">Recovery codes have been generated for emergency access</li>
    </ul>

    @component('emails.components.alert', ['type' => 'warning'])
        <p style="margin: 0; color: #92400e;">
            <strong>Please note:</strong> Keep your recovery codes in a safe place. You'll need them if you lose access to your authenticator app.
        </p>
    @endcomponent

    <p class="email-text">
        Enabled on: {{ now()->format('F j, Y \a\t g:i A') }}
    </p>

    @component('emails.components.button', ['url' => config('app.url') . '/settings/security'])
        View Security Settings
    @endcomponent

    <hr class="email-divider">

    @component('emails.components.alert', ['type' => 'danger'])
        <p style="margin: 0; color: #991b1b;">
            <strong>Didn't enable this?</strong><br>
            If you didn't enable two-factor authentication, please reset your password and contact our support team.
        </p>
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

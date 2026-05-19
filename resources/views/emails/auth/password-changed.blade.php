@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Password Successfully Changed</h1>

    <p class="email-text">
        Hi {{ $user->name }},
    </p>

    <p class="email-text">
        This email confirms that your password was successfully changed on {{ now()->format('F j, Y \a\t g:i A') }}.
    </p>

    @component('emails.components.alert', ['type' => 'success'])
        <p style="margin: 0; color: #065f46;">
            <strong>✓ Your password has been updated</strong><br>
            You can now use your new password to sign in to your account.
        </p>
    @endcomponent

    <p class="email-text">
        <strong>Details of the change:</strong>
    </p>

    <ul style="color: #4b5563; margin: 16px 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;">Date: {{ now()->format('F j, Y') }}</li>
        <li style="margin-bottom: 8px;">Time: {{ now()->format('g:i A T') }}</li>
        @if(isset($ipAddress))
        <li style="margin-bottom: 8px;">IP Address: {{ $ipAddress }}</li>
        @endif
    </ul>

    <hr class="email-divider">

    @component('emails.components.alert', ['type' => 'danger'])
        <p style="margin: 0; color: #991b1b;">
            <strong>Didn't make this change?</strong><br>
            If you didn't change your password, your account may be compromised. Please contact our support team immediately.
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => config('app.url') . '/settings/security'])
        Review Security Settings
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

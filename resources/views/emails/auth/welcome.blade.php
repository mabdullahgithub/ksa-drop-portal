@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Welcome to {{ config('app.name') }}</h1>

    <p class="email-text">
        Hi {{ $user->name }},
    </p>

    <p class="email-text">
        Your account has been created and is ready to use.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151;">
            <strong>Your account details:</strong><br>
            Email: {{ $user->email }}<br>
            Registered: {{ now()->format('F j, Y') }}
        </p>
    @endcomponent

    <p class="email-text">
        Here are some things you can do to get started:
    </p>

    <ul style="color: #4b5563; margin: 16px 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;">Complete your profile information</li>
        <li style="margin-bottom: 8px;">Explore the dashboard and features</li>
        <li style="margin-bottom: 8px;">Set up two-factor authentication for extra security</li>
        <li style="margin-bottom: 8px;">Customize your notification preferences</li>
    </ul>

    @component('emails.components.button', ['url' => config('app.url') . '/dashboard'])
        Go to Dashboard
    @endcomponent

    <hr class="email-divider">

    <p class="email-text">
        If you have any questions or need help getting started, don't hesitate to visit our
        <a href="{{ config('app.url') }}/help-center" style="color: #f26722; text-decoration: none;">Help Center</a>
        or contact our support team.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Welcome to the partner program</h1>

    <p class="email-text">Hi {{ $partner->user->name }},</p>

    <p class="email-text">
        Your application has been approved. Your welcome points are already in your balance, and your
        referral link is live.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Your code</strong><br>
            <span style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; letter-spacing: 1px;">{{ $partner->code }}</span>
            <br><br>
            <strong style="color: #111827;">Your referral link</strong><br>
            <a href="{{ $referralUrl }}">{{ $referralUrl }}</a>
        </p>
    @endcomponent

    <p class="email-text">
        Share the link anywhere: every seller who signs up through it is credited to you, and you earn
        points as they ship with us.
    </p>

    @component('emails.components.button', ['url' => $dashboardUrl])
        Open my partner dashboard
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

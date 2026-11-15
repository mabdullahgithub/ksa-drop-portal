@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">About your partner application</h1>

    <p class="email-text">Hi {{ $partner->user->name }},</p>

    <p class="email-text">
        Thank you for your interest in the {{ config('app.name') }} partner program. After reviewing your
        application, we are not able to approve it at this time.
    </p>

    @if ($partner->rejection_reason)
        @component('emails.components.alert', ['type' => 'warning'])
            <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
                {{ $partner->rejection_reason }}
            </p>
        @endcomponent
    @endif

    <p class="email-text">
        If anything has changed, reply to this email and we will take another look.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

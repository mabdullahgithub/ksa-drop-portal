@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Your payout has been sent</h1>

    <p class="email-text">Hi {{ $partner->user->name }},</p>

    <p class="email-text">
        We have sent your payout for request #{{ $payout->id }}.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Points redeemed</strong><br>{{ number_format($payout->points) }}
            <br><br>
            <strong style="color: #111827;">Amount sent</strong><br>{{ number_format((float) $payout->amount_paid, 2) }} {{ $payout->currency }}
            @if ($payout->admin_note)
                <br><br>
                <strong style="color: #111827;">Note</strong><br>{{ $payout->admin_note }}
            @endif
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => $dashboardUrl])
        See my payouts
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

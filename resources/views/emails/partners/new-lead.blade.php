@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">A new seller came through your link</h1>

    <p class="email-text">Hi {{ $partner->user->name }},</p>

    <p class="email-text">
        <strong>{{ $lead->name }}</strong>{{ $lead->company ? ' from ' . $lead->company : '' }} just asked to
        open a {{ config('app.name') }} account through your referral link. Our team will contact them to
        set up their account; you earn points once they start shipping.
    </p>

    @component('emails.components.button', ['url' => $dashboardUrl])
        See my referrals
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">We received your application</h1>

    <p class="email-text">Hi {{ $partner->user->name }},</p>

    <p class="email-text">
        Thanks for applying to the {{ config('app.name') }} partner program as
        <strong>{{ ucfirst($partner->type) }}</strong>. Our team reviews every application by hand,
        usually within two business days.
    </p>

    <p class="email-text">
        You can sign in now to see the status of your application. Your referral link and code become
        active the moment you are approved.
    </p>

    @component('emails.components.button', ['url' => $dashboardUrl])
        Open my partner area
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">New User Account Created</h1>

    <p class="email-text">
        Hi {{ $admin->name }},
    </p>

    <p class="email-text">
        A new user account has been created in your {{ config('app.name') }} workspace.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151;">
            <strong>User Details:</strong><br>
            Name: {{ $createdUser->name }}<br>
            Email: {{ $createdUser->email }}<br>
            @if(isset($role))
            Role: {{ $role }}<br>
            @endif
            Created by: {{ $createdBy->name }}<br>
            Created on: {{ now()->format('F j, Y \a\t g:i A') }}
        </p>
    @endcomponent

    @component('emails.components.button', ['url' => $userUrl ?? config('app.url') . '/team-management/users'])
        View User Details
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

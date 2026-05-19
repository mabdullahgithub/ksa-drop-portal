@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">User Account Updated</h1>

    <p class="email-text">
        Hi {{ $admin->name }},
    </p>

    <p class="email-text">
        A user account has been updated in your {{ config('app.name') }} workspace.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151;">
            <strong>User:</strong> {{ $updatedUser->name }} ({{ $updatedUser->email }})<br>
            <strong>Updated by:</strong> {{ $updatedBy->name }}<br>
            <strong>Date:</strong> {{ now()->format('F j, Y \a\t g:i A') }}
        </p>
    @endcomponent

    @if(isset($changes) && !empty($changes))
    <p class="email-text">
        <strong>Changes made:</strong>
    </p>

    <div style="background-color: #f9fafb; padding: 16px; border-radius: 4px; margin: 16px 0;">
        @foreach($changes as $field => $change)
        <p style="margin: 0 0 12px 0; color: #4b5563; font-size: 14px;">
            <strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong><br>
            <span style="color: #ef4444;">- {{ $change['from'] ?? 'N/A' }}</span><br>
            <span style="color: #22c55e;">+ {{ $change['to'] ?? 'N/A' }}</span>
        </p>
        @endforeach
    </div>
    @endif

    @component('emails.components.button', ['url' => $userUrl ?? config('app.url') . '/team-management/users'])
        View User Details
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">Settings Updated</h1>

    <p class="email-text">
        Hi {{ $user->name }},
    </p>

    <p class="email-text">
        Your {{ $settingType ?? 'account' }} settings have been successfully updated.
    </p>

    @component('emails.components.alert', ['type' => 'success'])
        <p style="margin: 0; color: #065f46;">
            <strong>✓ Changes saved</strong><br>
            Your settings were updated on {{ now()->format('F j, Y \a\t g:i A') }}
        </p>
    @endcomponent

    @if(isset($changes) && !empty($changes))
    <p class="email-text">
        <strong>Changes made:</strong>
    </p>

    <div style="background-color: #f9fafb; padding: 16px; border-radius: 4px; margin: 16px 0;">
        @foreach($changes as $field => $change)
        <p style="margin: 0 0 12px 0; color: #4b5563; font-size: 14px;">
            @if(is_array($change) && isset($change['from']) && isset($change['to']))
                <strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong><br>
                <span style="color: #ef4444;">From: {{ $change['from'] }}</span><br>
                <span style="color: #22c55e;">To: {{ $change['to'] }}</span>
            @else
                <strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong> {{ is_string($change) ? $change : json_encode($change) }}
            @endif
        </p>
        @endforeach
    </div>
    @endif

    @component('emails.components.button', ['url' => config('app.url') . '/settings'])
        View Settings
    @endcomponent

    <hr class="email-divider">

    <p class="email-text text-muted">
        If you didn't make these changes, please contact our support team.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

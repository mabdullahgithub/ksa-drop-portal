@extends('emails.layouts.default')

@section('content')
    <h1 class="email-title">You have a new reply</h1>

    <p class="email-text">
        {{ $agent }} replied to your support chat <strong>{{ $conversation->subject }}</strong> ({{ $conversation->reference }}).
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151; white-space: pre-line;">{{ \Illuminate\Support\Str::limit((string) $reply->body, 600) ?: ($reply->attachments->contains('is_voice', true) ? 'Sent you a voice message.' : ($reply->shared_items ? 'Shared an order or product with you.' : 'Sent you a file.')) }}</p>
    @endcomponent

    @component('emails.components.button', ['url' => $url])
        Open the chat
    @endcomponent

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection

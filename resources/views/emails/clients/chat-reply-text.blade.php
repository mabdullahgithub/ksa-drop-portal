{{ config('app.name') }}
{{ str_repeat('=', strlen(config('app.name'))) }}

You have a new reply

{{ $agent }} replied to your support chat "{{ $conversation->subject }}" ({{ $conversation->reference }}).

{{ \Illuminate\Support\Str::limit((string) $reply->body, 600) ?: ($reply->attachments->contains('is_voice', true) ? 'Sent you a voice message.' : ($reply->shared_items ? 'Shared an order or product with you.' : 'Sent you a file.')) }}

Open the chat:
{{ $url }}

---
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

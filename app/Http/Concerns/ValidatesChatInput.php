<?php

namespace App\Http\Concerns;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\ChatAttachmentStore;
use App\Services\Chat\ChatOrigin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The validation shared by the team's and the portal's chat endpoints.
 */
trait ValidatesChatInput
{
    /** Where this request came from, kept with the message it sends. */
    private function origin(Request $request): array
    {
        return app(ChatOrigin::class)->capture($request);
    }

    /** The orders and products a client attached; ownership is checked by ChatSharedItems. */
    private function sharedItemRules(bool $allowed): array
    {
        $max = config('chat.max_shared_items');

        return $allowed
            ? [
                'orders' => ['nullable', 'array', "max:{$max}"],
                'orders.*' => ['integer'],
                'products' => ['nullable', 'array', "max:{$max}"],
                'products.*' => ['integer'],
            ]
            : ['orders' => ['prohibited'], 'products' => ['prohibited']];
    }

    private function refuseTooManyItems(array $validated): void
    {
        $count = count($validated['orders'] ?? []) + count($validated['products'] ?? []);

        abort_if($count > config('chat.max_shared_items'), 422, 'Attach up to ' . config('chat.max_shared_items') . ' orders and products at a time.');
    }

    /** A message: some text, files, a voice recording, or (from a client) orders and products. */
    protected function validateMessage(Request $request, bool $allowNotes = false, bool $allowItems = false): array
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:' . config('chat.max_body'), 'required_without_all:files,voice,orders,products'],
            'kind' => ['nullable', Rule::in($allowNotes ? [ChatMessage::MESSAGE, ChatMessage::NOTE] : [ChatMessage::MESSAGE])],
            'client_uuid' => ['nullable', 'uuid'],
            ...ChatAttachmentStore::rules(),
            ...ChatAttachmentStore::voiceRules(),
            ...$this->sharedItemRules($allowItems),
            'client_info' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->refuseTooManyItems($validated);

        return [
            'kind' => $validated['kind'] ?? ChatMessage::MESSAGE,
            'body' => $validated['body'] ?? null,
            'client_uuid' => $validated['client_uuid'] ?? null,
            'files' => $request->file('files', []),
            'voice' => $request->file('voice'),
            'voice_duration' => isset($validated['voice_duration']) ? (int) $validated['voice_duration'] : null,
            'orders' => $validated['orders'] ?? [],
            'products' => $validated['products'] ?? [],
            'origin' => $this->origin($request),
        ];
    }

    /** A new conversation: what it is about and the first message. */
    protected function validateConversation(Request $request, bool $byTeam): array
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(ChatConversation::CATEGORIES)],
            'priority' => [$byTeam ? 'nullable' : 'prohibited', Rule::in(ChatConversation::PRIORITIES)],
            'order_id' => ['nullable', 'integer'],
            'body' => ['nullable', 'string', 'max:' . config('chat.max_body'), 'required_without_all:files,orders,products'],
            'client_uuid' => ['nullable', 'uuid'],
            ...ChatAttachmentStore::rules(),
            // A client's new chat may carry orders and products; the team's may not.
            ...$this->sharedItemRules(! $byTeam),
            'client_info' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->refuseTooManyItems($validated);

        return $validated + ['files' => $request->file('files', []), 'origin' => $this->origin($request)];
    }
}

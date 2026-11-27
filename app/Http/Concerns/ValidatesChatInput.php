<?php

namespace App\Http\Concerns;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\ChatAttachmentStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The validation shared by the team's and the portal's chat endpoints.
 */
trait ValidatesChatInput
{
    /** A message: some text, some files, or both. */
    protected function validateMessage(Request $request, bool $allowNotes = false): array
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:' . config('chat.max_body'), 'required_without:files'],
            'kind' => ['nullable', Rule::in($allowNotes ? [ChatMessage::MESSAGE, ChatMessage::NOTE] : [ChatMessage::MESSAGE])],
            'client_uuid' => ['nullable', 'uuid'],
            ...ChatAttachmentStore::rules(),
        ]);

        return [
            'kind' => $validated['kind'] ?? ChatMessage::MESSAGE,
            'body' => $validated['body'] ?? null,
            'client_uuid' => $validated['client_uuid'] ?? null,
            'files' => $request->file('files', []),
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
            'body' => ['nullable', 'string', 'max:' . config('chat.max_body'), 'required_without:files'],
            'client_uuid' => ['nullable', 'uuid'],
            ...ChatAttachmentStore::rules(),
        ]);

        return $validated + ['files' => $request->file('files', [])];
    }
}

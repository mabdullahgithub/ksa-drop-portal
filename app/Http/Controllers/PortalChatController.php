<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Concerns\ValidatesChatInput;
use App\Http\Controllers\Api\ChatController;
use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Services\Chat\ChatAttachmentStore;
use App\Services\Chat\ChatBroadcaster;
use App\Services\Chat\ChatMessenger;
use App\Services\Chat\ChatPresenter;
use App\Services\Chat\ChatUnread;
use App\Services\Chat\ChatWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's Support page: their conversations with the team.
 *
 * A client only ever reaches their own conversations, sees no internal notes
 * or team-only events, and gets file links through this controller.
 *
 * Someone on the team viewing the portal as the client (impersonating) can
 * read everything here but send nothing, and reading does not mark anything
 * read for the client.
 */
class PortalChatController extends Controller
{
    use ResolvesPortalClient, ValidatesChatInput;

    private const PER_PAGE = 30;

    public function __construct(
        private ChatWorkflow $workflow,
        private ChatMessenger $messenger,
        private ChatBroadcaster $broadcaster,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $client = $this->client();

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'resolved', 'closed', 'any'])],
            'cursor' => ['nullable', 'string'],
        ]);

        $query = ChatConversation::query()
            ->where('client_id', $client->id)
            ->with(ChatPresenter::relations(false))
            ->when(($validated['status'] ?? 'any') !== 'any', fn ($q) => match ($validated['status']) {
                'active' => $q->active(),
                default => $q->where('status', $validated['status']),
            });

        ChatUnread::withCounts($query, (int) $client->user_id, false);

        $page = $query->orderByDesc('last_message_at')->orderByDesc('id')->cursorPaginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($page->items())->map(fn (ChatConversation $c) => ChatPresenter::conversation($c, false))->values(),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function show(ChatConversation $conversation): JsonResponse
    {
        $this->own($conversation);

        return response()->json([
            'conversation' => ChatPresenter::conversation($conversation->load(ChatPresenter::relations(false)), false),
            'team_read_up_to' => ChatUnread::teamReadUpTo($conversation),
            'read_only' => $this->impersonating(),
        ]);
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->own($conversation);

        return response()->json(ChatController::page($request, $conversation->messages()->visibleToClient(), forTeam: false));
    }

    public function store(Request $request): JsonResponse
    {
        $client = $this->client();
        $this->refuseWhileImpersonating();

        $input = $this->validateConversation($request, byTeam: false);

        $conversation = $this->workflow->open($client, $request->user(), ChatConversation::BY_CLIENT, $input);

        return response()->json([
            'conversation' => ChatPresenter::conversation($conversation->load(ChatPresenter::relations(false)), false),
        ], 201);
    }

    public function reply(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->own($conversation);
        $this->refuseWhileImpersonating();

        $message = $this->messenger->send($conversation->load('client'), $request->user(), ChatMessage::FROM_CLIENT, $this->validateMessage($request));

        return response()->json(['message' => ChatPresenter::message($message->load(['sender', 'attachments']), false)], 201);
    }

    public function read(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->own($conversation);

        $requested = (int) $request->validate(['message_id' => ['required', 'integer', 'min:1']])['message_id'];

        // Looking as the client is not the client reading it.
        if ($this->impersonating()) {
            return response()->json(['up_to' => 0]);
        }

        $upTo = min($requested, (int) $conversation->messages()->visibleToClient()->max('id'));

        if ($upTo > 0) {
            ChatUnread::markRead($conversation, $request->user()->id, $upTo);
            $this->broadcaster->read($conversation->load('client'), $request->user()->id, byClient: true, upTo: $upTo);
        }

        return response()->json(['up_to' => $upTo]);
    }

    public function resolve(Request $request, ChatConversation $conversation): JsonResponse
    {
        return $this->move($request, $conversation, ChatConversation::RESOLVED);
    }

    public function reopen(Request $request, ChatConversation $conversation): JsonResponse
    {
        return $this->move($request, $conversation, ChatConversation::OPEN);
    }

    /** The client's own orders, to link one to a new conversation. */
    public function orders(Request $request): JsonResponse
    {
        $client = $this->client();

        $term = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        return response()->json(['data' => ChatController::orderLookup($client->id, $term)]);
    }

    public function attachment(ChatAttachment $attachment): StreamedResponse
    {
        $client = $this->client();
        $message = ChatMessage::find($attachment->message_id);

        abort_unless($message && ! $message->internal && $attachment->conversation?->client_id === $client->id, 404);

        return ChatAttachmentStore::response($attachment);
    }

    private function move(Request $request, ChatConversation $conversation, string $to): JsonResponse
    {
        $this->own($conversation);
        $this->refuseWhileImpersonating();

        $conversation = $this->workflow->changeStatus($conversation, $request->user(), $to, asClient: true);

        return response()->json([
            'conversation' => ChatPresenter::conversation($conversation->unsetRelations()->load(ChatPresenter::relations(false)), false),
        ]);
    }

    private function client(): Client
    {
        return $this->resolveClient() ?? abort(403);
    }

    /** Another client's conversation is a 404: nothing about it is given away. */
    private function own(ChatConversation $conversation): void
    {
        abort_unless($conversation->client_id === $this->client()->id, 404);
    }

    private function impersonating(): bool
    {
        return session()->has('impersonate.admin_id');
    }

    private function refuseWhileImpersonating(): void
    {
        abort_if($this->impersonating(), 403, 'You are viewing the portal as this client. Reply from the Live chat inbox instead.');
    }
}

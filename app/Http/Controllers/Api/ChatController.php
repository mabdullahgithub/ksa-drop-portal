<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesChatInput;
use App\Http\Controllers\Controller;
use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Services\Chat\ChatAttachmentStore;
use App\Services\Chat\ChatBroadcaster;
use App\Services\Chat\ChatMessenger;
use App\Services\Chat\ChatPresenter;
use App\Services\Chat\ChatUnread;
use App\Services\Chat\ChatVisibility;
use App\Services\Chat\ChatWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The team's live chat inbox. Each route is gated on its own permission
 * (routes/web.php). Beyond that, every conversation is checked with
 * ChatVisibility (client access), and one the person may not see is a 404,
 * never a 403, so nothing about it is given away.
 */
class ChatController extends Controller
{
    use ValidatesChatInput;

    private const PER_PAGE = 30;

    private const MESSAGES_PER_PAGE = 50;

    public function __construct(
        private ChatWorkflow $workflow,
        private ChatMessenger $messenger,
        private ChatBroadcaster $broadcaster,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['nullable', Rule::in([...ChatConversation::STATUSES, 'active', 'any'])],
            'priority' => ['nullable', Rule::in(ChatConversation::PRIORITIES)],
            'category' => ['nullable', Rule::in(ChatConversation::CATEGORIES)],
            'client_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ]);

        $status = $validated['status'] ?? 'active';

        $query = ChatConversation::query()
            ->with(ChatPresenter::relations(true))
            ->tap(fn (Builder $q) => $this->filterStatus($q, $status))
            ->when($validated['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($validated['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($validated['client_id'] ?? null, fn ($q, $client) => $q->where('client_id', $client))
            ->when(trim($validated['q'] ?? ''), fn ($q, $term) => $this->search($q, $term));

        ChatUnread::withCounts($query, $user->id, true);

        $page = $query->orderByDesc('last_message_at')->orderByDesc('id')->cursorPaginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($page->items())->map(fn (ChatConversation $c) => ChatPresenter::conversation($c, true))->values(),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function show(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        $conversation->load(ChatPresenter::relations(true));

        // The whole client for the side panel; the list's eager load only has a few columns.
        $client = $conversation->client()->with('user:id,email')->first();

        return response()->json([
            'conversation' => ChatPresenter::conversation($conversation, true),
            'client_read_up_to' => ChatUnread::clientReadUpTo($conversation),
            'context' => [
                'client' => $client ? [
                    'id' => $client->id,
                    'company_name' => $client->company_name,
                    'short_id' => $client->short_id,
                    'status' => $client->status,
                    'type_label' => $client->type_label,
                    'contact_person' => $client->contact_person,
                    'phone' => $client->phone,
                    'email' => $client->user?->email,
                    'city' => $client->city,
                ] : null,
                'other_conversations' => ChatConversation::query()
                    ->where('client_id', $conversation->client_id)
                    ->whereKeyNot($conversation->id)
                    ->latest('last_message_at')
                    ->limit(5)
                    ->get(['id', 'reference', 'subject', 'status', 'last_message_at'])
                    ->map(fn (ChatConversation $c) => [
                        'id' => $c->id,
                        'reference' => $c->reference,
                        'subject' => $c->subject,
                        'status' => $c->status,
                        'last_message_at' => $c->last_message_at?->toIso8601String(),
                    ]),
            ],
            'permissions' => [
                'reply' => $request->user()->can('reply chats'),
                'note' => $request->user()->can('add chat notes'),
                'status' => $request->user()->can('change chat status'),
                'edit' => $request->user()->can('edit chat details'),
                'delete' => $request->user()->can('delete chats'),
            ],
        ]);
    }

    /** A page of the timeline: older with before_id, newer with after_id, the latest with neither. */
    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        return response()->json($this->page($request, $conversation->messages()));
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->validateConversation($request, byTeam: true);
        $request->validate(['client_id' => ['required', 'integer']]);

        // The model's client scope already hides clients this person may not see.
        $client = Client::findOrFail($request->integer('client_id'));
        abort_unless(ChatVisibility::canSeeClient($request->user(), $client->id), 404);

        $conversation = $this->workflow->open($client, $request->user(), ChatConversation::BY_STAFF, $input);

        return response()->json([
            'conversation' => ChatPresenter::conversation($conversation->load(ChatPresenter::relations(true)), true),
        ], 201);
    }

    public function reply(Request $request, ChatConversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSee($user, $conversation);

        $input = $this->validateMessage($request, allowNotes: true);
        abort_unless($user->can($input['kind'] === ChatMessage::NOTE ? 'add chat notes' : 'reply chats'), 403);

        $message = $this->messenger->send($conversation->load('client'), $user, ChatMessage::FROM_STAFF, $input);

        return response()->json(['message' => ChatPresenter::message($message->load(['sender', 'attachments']), true)], 201);
    }

    public function read(Request $request, ChatConversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSee($user, $conversation);

        $upTo = min((int) $request->validate(['message_id' => ['required', 'integer', 'min:1']])['message_id'], (int) $conversation->messages()->max('id'));

        if ($upTo > 0) {
            ChatUnread::markRead($conversation, $user->id, $upTo);
            $this->broadcaster->read($conversation->load('client'), $user->id, byClient: false, upTo: $upTo);
        }

        return response()->json(['up_to' => $upTo]);
    }

    public function status(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        $to = $request->validate(['status' => ['required', Rule::in(ChatConversation::STATUSES)]])['status'];

        return $this->respond($this->workflow->changeStatus($conversation, $request->user(), $to));
    }

    public function update(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        $input = $request->validate([
            'subject' => ['sometimes', 'string', 'max:160'],
            'category' => ['sometimes', Rule::in(ChatConversation::CATEGORIES)],
            'priority' => ['sometimes', Rule::in(ChatConversation::PRIORITIES)],
            'order_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        return $this->respond($this->workflow->updateDetails($conversation, $request->user(), $input));
    }

    public function destroy(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        $this->workflow->delete($conversation->load('client'));

        return response()->json(['message' => 'Chat deleted']);
    }

    /** The numbers above the inbox. */
    public function stats(Request $request): JsonResponse
    {
        // The request's client scope keeps these to clients this person may see.
        $base = fn () => ChatConversation::query();

        return response()->json([
            'open' => $base()->active()->count(),
            'waiting_on_us' => $base()->where('status', ChatConversation::OPEN)->count(),
            'resolved_today' => $base()->where('resolved_at', '>=', now()->startOfDay())->count(),
            'avg_response_minutes' => $this->averageResponseMinutes($base()->select('id'), now()->subDays(7)),
        ]);
    }

    /**
     * How long clients waited for an answer, on average, over the replies the
     * team sent since $since. A wait starts at the client's first unanswered
     * message (more messages while waiting don't restart it) and ends at the
     * team's next reply. Notes and status lines are not replies.
     */
    private function averageResponseMinutes(Builder $conversations, Carbon $since): ?int
    {
        $answered = ChatMessage::query()
            ->whereIn('conversation_id', $conversations)
            ->where('kind', ChatMessage::MESSAGE)
            ->where('sender_type', ChatMessage::FROM_STAFF)
            ->where('created_at', '>=', $since)
            ->select('conversation_id');

        // The whole thread of those chats: a wait can have begun before $since.
        $messages = ChatMessage::query()
            ->whereIn('conversation_id', $answered)
            ->where('kind', ChatMessage::MESSAGE)
            ->orderBy('conversation_id')
            ->orderBy('id')
            ->select(['id', 'conversation_id', 'sender_type', 'created_at'])
            ->cursor();

        $total = $replies = 0;
        $conversation = $waitingSince = null;

        foreach ($messages as $message) {
            if ($message->conversation_id !== $conversation) {
                $conversation = $message->conversation_id;
                $waitingSince = null;
            }

            if ($message->sender_type === ChatMessage::FROM_CLIENT) {
                $waitingSince ??= $message->created_at;

                continue;
            }

            if ($waitingSince !== null && $message->created_at >= $since) {
                $total += $waitingSince->diffInSeconds($message->created_at);
                $replies++;
            }

            $waitingSince = null;
        }

        return $replies === 0 ? null : (int) round($total / $replies / 60);
    }

    /** Clients for the "new chat" picker: the ones this person may see. */
    public function clients(Request $request): JsonResponse
    {
        $term = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));

        return response()->json([
            'data' => Client::query()
                ->where('status', '!=', Client::STATUS_PENDING)
                ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('company_name', 'like', "%{$term}%")->orWhere('short_id', 'like', "%{$term}%")))
                ->orderBy('company_name')
                ->limit(20)
                ->get(['id', 'company_name', 'short_id', 'logo'])
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'company_name' => $client->company_name,
                    'short_id' => $client->short_id,
                ]),
        ]);
    }

    /** A client's orders, to link one to a conversation. */
    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['data' => $this->orderLookup($validated['client_id'], $validated['q'] ?? '')]);
    }

    public function attachment(Request $request, ChatAttachment $attachment): StreamedResponse
    {
        $conversation = ChatConversation::find($attachment->conversation_id);
        abort_unless($conversation && ChatVisibility::canSee($request->user(), $conversation), 404);

        return ChatAttachmentStore::response($attachment);
    }

    /** @return list<array<string, mixed>> */
    public static function orderLookup(int $clientId, string $term): array
    {
        return Order::query()
            ->where('client_id', $clientId)
            ->when(trim($term), fn ($q, $t) => $q->where('order_number', 'like', '%' . addcslashes($t, '%_\\') . '%'))
            ->latest('id')
            ->limit(20)
            ->get(['id', 'order_number', 'fulfillment_status', 'total', 'currency', 'created_at'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->fulfillment_status,
                'total' => $order->total,
                'currency' => $order->currency,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return array{data: list<array>, has_more: bool} */
    public static function page(Request $request, $messages, bool $forTeam = true): array
    {
        $validated = $request->validate([
            'before_id' => ['nullable', 'integer'],
            'after_id' => ['nullable', 'integer'],
        ]);

        $query = $messages->with(['sender', 'attachments']);

        if (isset($validated['after_id'])) {
            $rows = $query->where('id', '>', $validated['after_id'])->orderBy('id')->limit(200)->get();

            return ['data' => $rows->map(fn ($m) => ChatPresenter::message($m, $forTeam))->values()->all(), 'has_more' => false];
        }

        $rows = $query
            ->when($validated['before_id'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::MESSAGES_PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::MESSAGES_PER_PAGE;

        return [
            'data' => $rows->take(self::MESSAGES_PER_PAGE)->reverse()->map(fn ($m) => ChatPresenter::message($m, $forTeam))->values()->all(),
            'has_more' => $hasMore,
        ];
    }

    private function respond(ChatConversation $conversation): JsonResponse
    {
        $conversation->unsetRelations()->load(ChatPresenter::relations(true));

        return response()->json(['conversation' => ChatPresenter::conversation($conversation, true)]);
    }

    private function authorizeSee(User $user, ChatConversation $conversation): void
    {
        abort_unless(ChatVisibility::canSee($user, $conversation), 404);
    }

    private function filterStatus(Builder $query, string $status): void
    {
        match ($status) {
            'any' => null,
            'active' => $query->active(),
            default => $query->where('status', $status),
        };
    }

    private function search(Builder $query, string $term): void
    {
        $like = '%' . addcslashes($term, '%_\\') . '%';

        $query->where(fn (Builder $q) => $q
            ->where('subject', 'like', $like)
            ->orWhere('reference', 'like', $like)
            ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', $like)->orWhere('short_id', 'like', $like))
            ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)));
    }
}

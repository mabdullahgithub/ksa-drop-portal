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
use App\Services\Chat\ChatSharedItems;
use App\Services\Chat\ChatUnread;
use App\Services\Chat\ChatVisibility;
use App\Services\Chat\ChatWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    private const ACTIVITY_LIMIT = 15;

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

        // Everything but the two dropdowns, which the counts need on their own.
        $base = fn (): Builder => ChatConversation::query()
            ->when($validated['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($validated['client_id'] ?? null, fn ($q, $client) => $q->where('client_id', $client))
            ->when(trim($validated['q'] ?? ''), fn ($q, $term) => $this->search($q, $term));

        $priority = $validated['priority'] ?? null;

        $query = $base()
            ->with(ChatPresenter::relations(true))
            ->tap(fn (Builder $q) => $this->filterStatus($q, $status))
            ->when($priority, fn ($q) => $q->where('priority', $priority));

        ChatUnread::withCounts($query, $user->id, true);

        $page = $query->orderByDesc('last_message_at')->orderByDesc('id')->cursorPaginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($page->items())->map(fn (ChatConversation $c) => ChatPresenter::conversation($c, true))->values(),
            'next_cursor' => $page->nextCursor()?->encode(),
            // With the first page only; "load more" doesn't change them.
            'counts' => isset($validated['cursor']) ? null : $this->filterCounts($base, $status, $priority),
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
                'opened_by_name' => $conversation->creator?->name,
                // What happened lately: changes and notes, for the panel's log.
                'activity' => $conversation->messages()
                    ->whereIn('kind', [ChatMessage::EVENT, ChatMessage::NOTE])
                    ->with(['sender', 'attachments'])
                    ->latest('id')
                    ->limit(self::ACTIVITY_LIMIT)
                    ->get()
                    ->map(fn (ChatMessage $m) => ChatPresenter::message($m, true)),
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
                'share_orders' => $request->user()->can('view orders'),
                'share_products' => $request->user()->can('view client products') && (bool) $client?->is_fulfilment,
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

        $input = $this->validateMessage($request, allowNotes: true, allowItems: true);
        abort_unless($user->can($input['kind'] === ChatMessage::NOTE ? 'add chat notes' : 'reply chats'), 403);
        // Sharing a record needs the right to see it.
        abort_if($input['orders'] !== [] && ! $user->can('view orders'), 403, "You don't have permission to share orders.");
        abort_if($input['products'] !== [] && ! $user->can('view client products'), 403, "You don't have permission to share inventory.");

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

    /** Just the sidebar number, so keeping it current never re-runs a whole page. */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread' => ChatUnread::staffTotal($request->user())]);
    }

    /** The numbers above the inbox. */
    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->countStats());
    }

    /** @return array{open: int, waiting_on_us: int, resolved_today: int, avg_response_minutes: ?int} */
    private function countStats(): array
    {
        // One pass over the conversations. The request's client scope keeps it
        // to clients this person may see.
        $active = "'" . implode("','", ChatConversation::ACTIVE) . "'";
        $counts = ChatConversation::query()->toBase()
            ->selectRaw("sum(case when status in ({$active}) then 1 else 0 end) as open_count")
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as waiting_count', [ChatConversation::OPEN])
            ->selectRaw('sum(case when resolved_at >= ? then 1 else 0 end) as resolved_count', [now()->startOfDay()])
            ->first();

        // Each reply noted how long the client had waited (ChatMessenger).
        $average = ChatMessage::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('response_seconds')
            ->whereIn('conversation_id', ChatConversation::query()->select('id'))
            ->avg('response_seconds');

        return [
            'open' => (int) $counts->open_count,
            'waiting_on_us' => (int) $counts->waiting_count,
            'resolved_today' => (int) $counts->resolved_count,
            'avg_response_minutes' => $average === null ? null : (int) round($average / 60),
        ];
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

    /**
     * Where one message was sent from (`view chat message details`). The only
     * place this leaves the server; the portal has no such endpoint.
     */
    public function messageDetails(Request $request, ChatConversation $conversation, int $message): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        $found = $conversation->messages()->findOrFail($message);

        return response()->json(['origin' => $found->origin])->header('Cache-Control', 'no-store, private');
    }

    /** This chat's client's orders, to attach to a message. */
    public function shareOrders(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        return response()->json(['data' => ChatSharedItems::orderOptions($conversation->client, $this->term($request))]);
    }

    /** This chat's client's inventory, to attach to a message. */
    public function shareProducts(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeSee($request->user(), $conversation);

        return response()->json(['data' => ChatSharedItems::productOptions($conversation->client, $this->term($request))]);
    }

    private function term(Request $request): string
    {
        return (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
    }

    public function attachment(Request $request, ChatAttachment $attachment): BinaryFileResponse
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

    /**
     * The number beside each option of the status and priority dropdowns:
     * what the list would hold if that option were picked, so each dropdown
     * counts within the other one's choice (and the search).
     *
     * @param  \Closure(): Builder  $base
     * @return array{status: array<string, int>, priority: array<string, int>}
     */
    private function filterCounts(\Closure $base, string $status, ?string $priority): array
    {
        $grouped = fn (Builder $query, string $column) => $query
            ->toBase()
            ->selectRaw("{$column}, count(*) as total")
            ->groupBy($column)
            ->pluck('total', $column)
            ->map(fn ($total) => (int) $total);

        $byStatus = $grouped($base()->when($priority, fn ($q) => $q->where('priority', $priority)), 'status');
        $byPriority = $grouped($base()->tap(fn (Builder $q) => $this->filterStatus($q, $status)), 'priority');

        $statuses = collect(ChatConversation::STATUSES)->mapWithKeys(fn ($s) => [$s => $byStatus->get($s, 0)]);
        $priorities = collect(ChatConversation::PRIORITIES)->mapWithKeys(fn ($p) => [$p => $byPriority->get($p, 0)]);

        return [
            'status' => $statuses->all() + [
                'active' => $statuses->only(ChatConversation::ACTIVE)->sum(),
                'any' => $statuses->sum(),
            ],
            'priority' => $priorities->all() + ['any' => $priorities->sum()],
        ];
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read model behind the WhatsApp inbox.
 *
 * A "conversation" is just an order that has entered the confirmation flow —
 * there is no separate conversation entity, because the order *is* the subject
 * of every message. Keeping it that way means the inbox can link straight into
 * the order without an extra join table to keep in sync.
 */
class WhatsAppConversationController extends Controller
{
    /**
     * Paginated conversation list for the left pane.
     *
     * Messages are eager-loaded rather than fetched per row: a conversation
     * holds at most a handful (ping, follow-up, replies), so one extra query
     * beats N+1 and beats the contortions needed to limit a hasMany per parent.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Order::withoutGlobalScope('shopify_visible')
            ->whereNotNull('whatsapp_status')
            ->with(['client:id,company_name', 'whatsappMessages'])
            ->orderByRaw('COALESCE(whatsapp_replied_at, whatsapp_followup_sent_at, whatsapp_sent_at) DESC');

        if ($status = $request->input('status')) {
            if ($status === 'needs_attention') {
                // A customer replied and nobody has acted on it yet — the only
                // bucket that represents work waiting on an agent.
                $query->where('whatsapp_status', Order::WHATSAPP_REPLIED);
            } elseif ($status !== 'all') {
                $query->where('whatsapp_status', $status);
            }
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp_phone_e164', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Order $o) => $this->summarise($o))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * One conversation: the full thread plus the order context the agent needs
     * without leaving the inbox.
     */
    public function show(Order $order): JsonResponse
    {
        abort_unless($order->whatsapp_status !== null, 404);

        $order->load(['client:id,company_name', 'items', 'whatsappMessages.sentBy:id,name', 'latestShipment']);

        $windowExpiresAt = $order->whatsAppWindowExpiresAt();

        return response()->json([
            'conversation' => $this->summarise($order),
            // Gates the reply box: free-form is only legal inside WhatsApp's
            // 24h customer service window.
            'window' => [
                'open' => $order->whatsAppWindowIsOpen(),
                'expires_at' => $windowExpiresAt,
            ],
            'messages' => $order->whatsappMessages
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn (WhatsAppMessage $m) => [
                    'id' => $m->id,
                    'direction' => $m->direction,
                    'body' => $m->body,
                    'template_key' => $m->template_key,
                    'sent_by' => $m->sentBy?->name,
                    'status' => $m->status,
                    'created_at' => $m->created_at,
                    'sent_at' => $m->sent_at,
                    'delivered_at' => $m->delivered_at,
                    'read_at' => $m->read_at,
                    'failed_at' => $m->failed_at,
                    'error_code' => $m->error_code,
                    'error_message' => $m->error_message,
                ]),
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'shipping_name' => $order->shipping_name,
                'shipping_address1' => $order->shipping_address1,
                'shipping_address2' => $order->shipping_address2,
                'shipping_city' => $order->shipping_city,
                'shipping_province' => $order->shipping_province,
                'shipping_country' => $order->shipping_country,
                'total' => $order->total,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method,
                'is_cod' => $order->isCashOnDelivery(),
                'financial_status' => $order->financial_status,
                'fulfillment_status' => $order->fulfillment_status,
                'call_status' => $order->call_status,
                'call_attempts' => $order->call_attempts,
                'call_notes' => $order->call_notes,
                'last_called_at' => $order->last_called_at,
                'tags' => $order->tags,
                'created_at' => $order->created_at,
                'client' => $order->client?->only(['id', 'company_name']),
                // order_items columns are lineitem_*-prefixed; flatten them to
                // plain names for the panel.
                'items' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->lineitem_name,
                    'quantity' => $i->lineitem_quantity,
                    'price' => $i->lineitem_price,
                ]),
                'tracking_number' => $order->latestShipment?->tracking_number,
            ],
        ]);
    }

    /**
     * Send an agent-typed reply into an open conversation.
     *
     * Guarded on the 24-hour customer service window rather than attempted and
     * left to fail: Meta rejects free-form outside it, and a clear 422 telling
     * the agent the window closed is far more useful than a generic Meta
     * error surfacing in the UI.
     */
    public function reply(Request $request, Order $order, MetaWhatsAppService $whatsapp)
    {
        abort_unless($order->whatsapp_status !== null, 404);

        $validated = $request->validate([
            'body' => 'required|string|max:1500',
        ]);

        if (! $order->whatsAppWindowIsOpen()) {
            return response()->json([
                'message' => 'The 24-hour reply window has closed. WhatsApp only allows an approved template now — call the customer instead.',
            ], 422);
        }

        try {
            $message = $whatsapp->sendFreeform($order, trim($validated['body']), $request->user()?->id);
        } catch (Throwable $e) {
            Log::channel('whatsapp')->error('Agent reply failed', [
                'order_id' => $order->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Could not send: ' . $e->getMessage()], 502);
        }

        Log::channel('whatsapp')->info('Agent reply sent', [
            'order_id' => $order->id,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Reply sent',
            'sent' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'body' => $message->body,
                'sent_by' => $request->user()?->name,
                'status' => $message->status,
                'created_at' => $message->created_at,
                'sent_at' => $message->sent_at,
                'delivered_at' => null,
                'read_at' => null,
                'failed_at' => null,
                'template_key' => null,
                'error_code' => null,
                'error_message' => null,
            ],
        ]);
    }

    /**
     * Counts for the filter tabs. One grouped query rather than six.
     */
    public function stats(): JsonResponse
    {
        $counts = Order::withoutGlobalScope('shopify_visible')
            ->whereNotNull('whatsapp_status')
            ->selectRaw('whatsapp_status, COUNT(*) as aggregate')
            ->groupBy('whatsapp_status')
            ->pluck('aggregate', 'whatsapp_status');

        // Delivery reach, counted off the order mirrors rather than the message
        // log so one query covers it. `read` is reported only for customers who
        // leave read receipts on, so it is a floor, never a true readership
        // figure — the UI has to say so wherever it shows this.
        $reach = Order::withoutGlobalScope('shopify_visible')
            ->whereNotNull('whatsapp_status')
            ->selectRaw('COUNT(whatsapp_delivered_at) as delivered, COUNT(whatsapp_read_at) as `read`')
            ->first();

        return response()->json([
            'all' => (int) $counts->sum(),
            'needs_attention' => (int) ($counts[Order::WHATSAPP_REPLIED] ?? 0),
            'sent' => (int) ($counts[Order::WHATSAPP_SENT] ?? 0),
            'followup_sent' => (int) ($counts[Order::WHATSAPP_FOLLOWUP_SENT] ?? 0),
            'replied' => (int) ($counts[Order::WHATSAPP_REPLIED] ?? 0),
            'confirmed' => (int) ($counts[Order::WHATSAPP_CONFIRMED] ?? 0),
            'graveyard' => (int) ($counts[Order::WHATSAPP_GRAVEYARD] ?? 0),
            'failed' => (int) ($counts[Order::WHATSAPP_FAILED] ?? 0),
            'delivered' => (int) ($reach->delivered ?? 0),
            'read' => (int) ($reach->read ?? 0),
        ]);
    }

    /**
     * List-row shape: enough to render the row and its preview without the
     * client having to fetch the thread.
     */
    private function summarise(Order $order): array
    {
        $messages = $order->whatsappMessages->sortBy([['created_at', 'asc'], ['id', 'asc']]);
        $last = $messages->last();
        $lastOutbound = $messages->where('direction', WhatsAppMessage::DIRECTION_OUTBOUND)->last();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name ?: $order->shipping_name,
            'phone' => $order->whatsapp_phone_e164 ?: $order->customer_phone,
            'client_name' => $order->client?->company_name,
            'total' => $order->total,
            'currency' => $order->currency,

            'whatsapp_status' => $order->whatsapp_status,
            'call_status' => $order->call_status,
            'tags' => $order->tags,

            'sent_at' => $order->whatsapp_sent_at,
            'followup_sent_at' => $order->whatsapp_followup_sent_at,
            'replied_at' => $order->whatsapp_replied_at,
            'delivered_at' => $order->whatsapp_delivered_at,
            'read_at' => $order->whatsapp_read_at,

            'last_message' => $last?->body,
            'last_message_at' => $last?->created_at,
            'last_message_direction' => $last?->direction,
            // Delivery state of the newest message we sent — what the ticks on
            // the row render from.
            'last_outbound_status' => $lastOutbound?->status,
            'message_count' => $messages->count(),

            // Drives the unread dot: the customer said something and the flow
            // hasn't been resolved by an agent yet.
            'needs_attention' => $order->whatsapp_status === Order::WHATSAPP_REPLIED,
        ];
    }
}

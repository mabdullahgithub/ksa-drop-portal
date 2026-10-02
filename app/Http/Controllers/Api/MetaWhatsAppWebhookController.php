<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tag;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\MetaWhatsAppService;
use App\Services\WhatsApp\ReplyIntent;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta's single WhatsApp Cloud API webhook.
 *
 *  - verify()  GET  /webhooks/whatsapp  — subscription handshake
 *  - handle()  POST /webhooks/whatsapp  — customer replies AND delivery receipts
 *
 * Unlike the Twilio integration this replaced (two flat form-encoded endpoints),
 * Meta delivers everything to one URL as nested JSON, batched:
 *
 *     entry[].changes[].value.messages[]  → inbound customer messages
 *     entry[].changes[].value.statuses[]  → sent/delivered/read/failed receipts
 *
 * A single POST can carry several entries, several changes, and both arrays at
 * once, so every level is looped rather than indexed at [0].
 *
 * Signature verification is an HMAC-SHA256 of the *raw request body* keyed on
 * the Meta app secret — not Twilio's URL-plus-sorted-params scheme. Nothing may
 * reshape the body before we read it.
 *
 * Always answers 2xx once the signature passes — including for payloads we
 * can't match to an order. Meta retries non-2xx for up to 7 days and
 * escalates to disabling the subscription, and there is nothing to gain from
 * redelivering a reply from a number we don't know.
 */
class MetaWhatsAppWebhookController extends Controller
{
    public function __construct(private MetaWhatsAppService $whatsapp) {}

    /**
     * Subscription handshake. Meta calls this once when the webhook URL is
     * saved in the app dashboard and expects the raw challenge echoed back as
     * plain text — a JSON-wrapped or quoted response fails verification.
     */
    public function verify(Request $request): Response
    {
        $expected = $this->whatsapp->webhookVerifyToken();

        if (! $expected) {
            Log::channel('whatsapp_webhooks')->error('Webhook verification rejected — no verify token configured');

            return response('Verify token not configured', 403);
        }

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode !== 'subscribe' || ! is_string($token) || ! hash_equals($expected, $token)) {
            Log::channel('whatsapp_webhooks')->warning('Webhook verification rejected — token mismatch');

            return response('Forbidden', 403);
        }

        Log::channel('whatsapp_webhooks')->info('Webhook subscription verified');

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Everything Meta pushes: inbound messages and status transitions, in the
     * same envelope.
     */
    public function handle(Request $request): Response
    {
        if (! $this->verifySignature($request)) {
            return response('Invalid signature', 403);
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                // One Meta app can serve several numbers. Ignore traffic for a
                // number that isn't the one this portal sends from.
                $deliveredFor = $value['metadata']['phone_number_id'] ?? null;
                $ours = $this->whatsapp->phoneNumberId();

                if ($ours && $deliveredFor && $deliveredFor !== $ours) {
                    continue;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    $this->handleInboundMessage($message, $value);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->handleStatus($status);
                }
            }
        }

        return response('', 200);
    }

    /**
     * Customer replied. Match the number back to the order we're chasing, log
     * the message, and act on what they said.
     *
     * @param  array<string,mixed>  $message
     * @param  array<string,mixed>  $value
     */
    private function handleInboundMessage(array $message, array $value): void
    {
        // Meta sends bare digits (966501234567); PhoneNumber::toE164 normalises
        // that to the +966… form the orders table stores.
        $from = PhoneNumber::toE164($message['from'] ?? null);
        $providerId = $message['id'] ?? null;
        $body = $this->extractBody($message);

        Log::channel('whatsapp_webhooks')->info('Inbound WhatsApp message', [
            'from' => $from,
            'id' => $providerId,
            'type' => $message['type'] ?? null,
            'body' => $body,
        ]);

        if (! $from || ! $providerId) {
            return;
        }

        $order = $this->resolveOrder($from);

        if (! $order) {
            Log::channel('whatsapp_webhooks')->info('Inbound WhatsApp message matched no order', ['from' => $from]);

            return;
        }

        // Meta redelivers a webhook until it gets a 2xx, and can deliver the
        // same message in more than one batch; the unique provider_message_id
        // keeps that from duplicating the conversation history.
        WhatsAppMessage::updateOrCreate(
            ['provider_message_id' => $providerId],
            [
                'order_id' => $order->id,
                'direction' => WhatsAppMessage::DIRECTION_INBOUND,
                'body' => $body,
                'to_number' => PhoneNumber::toE164($value['metadata']['display_phone_number'] ?? null),
                'from_number' => $from,
                'status' => 'received',
            ]
        );

        $this->applyReply($order, $body);

        // Blue ticks for the customer: someone is actually reading this.
        $this->whatsapp->markAsRead($providerId);
    }

    /**
     * Delivery lifecycle for a message we sent: accepted → sent → delivered → read.
     *
     * ⚠️ `read` only ever arrives if the customer has read receipts enabled in
     * their WhatsApp privacy settings — a large share of users don't. Absence of
     * a read receipt therefore means "unknown", not "unread", and nothing in the
     * follow-up logic may treat it as proof the message went unseen.
     *
     * @param  array<string,mixed>  $status
     */
    private function handleStatus(array $status): void
    {
        $providerId = $status['id'] ?? null;
        $state = $status['status'] ?? null;

        if (! $providerId || ! $state) {
            return;
        }

        $message = WhatsAppMessage::where('provider_message_id', $providerId)->first();

        if (! $message) {
            return;
        }

        $updates = ['status' => $state];

        // Meta has no `undelivered`; `failed` is the only terminal state, with
        // the actionable detail carried in the error code.
        match ($state) {
            'delivered' => $updates['delivered_at'] = $message->delivered_at ?? now(),
            'read' => $updates['read_at'] = $message->read_at ?? now(),
            'failed' => $updates['failed_at'] = $message->failed_at ?? now(),
            default => null,
        };

        if ($error = ($status['errors'][0] ?? null)) {
            $updates['error_code'] = (string) ($error['code'] ?? '');
            $updates['error_message'] = $error['error_data']['details'] ?? $error['title'] ?? $error['message'] ?? null;
        }

        $message->update($updates);

        // Mirror the latest timestamps onto the order so the orders list can
        // show "delivered / read at" without joining the message log.
        if ($order = $message->order) {
            if ($state === 'delivered' && ! $order->whatsapp_delivered_at) {
                $order->forceFill(['whatsapp_delivered_at' => now()])->saveQuietly();
            }

            if ($state === 'read' && ! $order->whatsapp_read_at) {
                $order->forceFill(['whatsapp_read_at' => now()])->saveQuietly();
            }
        }

        Log::channel('whatsapp_webhooks')->info('WhatsApp status update', [
            'id' => $providerId,
            'status' => $state,
            'order_id' => $message->order_id,
            'error_code' => $status['errors'][0]['code'] ?? null,
        ]);
    }

    /**
     * Pull readable text out of whichever message type arrived.
     *
     * Plain text covers the numbered replies the templates ask for, but a
     * customer can also tap a template quick-reply button or an interactive
     * button, which arrive under entirely different keys. All three feed the
     * same intent classifier.
     *
     * @param  array<string,mixed>  $message
     */
    private function extractBody(array $message): ?string
    {
        return match ($message['type'] ?? null) {
            'text' => $message['text']['body'] ?? null,
            // Quick-reply button on an approved template.
            'button' => $message['button']['text'] ?? $message['button']['payload'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? null,
            // Media and location carry no text to classify, but the caption is
            // worth logging — a customer photographing a building entrance is
            // answering the address question.
            'image', 'video', 'document', 'audio' => $message[$message['type']]['caption'] ?? null,
            default => null,
        };
    }

    /**
     * The most recent order we're actively chasing on this number. Scoped to
     * open conversations so a reply can't reopen an order settled weeks ago,
     * and ordered newest-first because a repeat customer may have several.
     */
    private function resolveOrder(string $phoneE164): ?Order
    {
        return Order::withoutGlobalScope('shopify_visible')
            ->where('whatsapp_phone_e164', $phoneE164)
            ->awaitingWhatsAppReply()
            ->orderByDesc('whatsapp_sent_at')
            ->first();
    }

    /**
     * Record the reply on the order and route it by intent. Confirmation is the
     * only outcome we act on automatically; everything else hands the order to
     * an agent with a tag that surfaces in the existing filtered views.
     */
    private function applyReply(Order $order, ?string $body): void
    {
        $intent = ReplyIntent::classify($body);

        $order->forceFill([
            'whatsapp_status' => $intent === ReplyIntent::CONFIRM
                ? Order::WHATSAPP_CONFIRMED
                : Order::WHATSAPP_REPLIED,
            'whatsapp_replied_at' => $order->whatsapp_replied_at ?? now(),
            'whatsapp_reply_message' => $body,
        ])->saveQuietly();

        [$tag, $color, $description] = match ($intent) {
            ReplyIntent::CONFIRM => ['Confirmed', '#22c55e', 'Customer confirmed the order over WhatsApp'],
            ReplyIntent::UPDATE_ADDRESS => ['Address Update Requested', '#3b82f6', 'Customer sent a new delivery address over WhatsApp'],
            ReplyIntent::CANCEL => ['Cancellation Requested', '#ef4444', 'Customer asked to cancel over WhatsApp'],
            default => ['Needs Review', '#f59e0b', 'Customer replied over WhatsApp — agent review needed'],
        };

        Tag::firstOrCreate(['name' => $tag], ['color' => $color, 'description' => $description]);

        $order->forceFill(['tags' => [$tag]])->saveQuietly();

        // A confirmation over WhatsApp settles what the phone call could not,
        // so the call disposition catches up too. saveQuietly() throughout
        // keeps OrderObserver from reacting to our own writes.
        if ($intent === ReplyIntent::CONFIRM && $order->call_status === Order::CALL_NO_ANSWER) {
            $order->forceFill(['call_status' => Order::CALL_CONFIRMED])->saveQuietly();
        }

        Log::channel('whatsapp_webhooks')->info('WhatsApp reply processed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'intent' => $intent,
        ]);
    }

    /**
     * Meta signs the raw request body with the app secret and sends it as
     * `X-Hub-Signature-256: sha256=<hex>`.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = $this->whatsapp->appSecret();

        if (! $secret) {
            Log::channel('whatsapp_webhooks')->error('WhatsApp webhook rejected — app secret not configured');

            return false;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        // getContent() and not the parsed input: the HMAC covers the exact bytes
        // Meta sent, so re-encoding the decoded JSON would not reproduce it.
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}

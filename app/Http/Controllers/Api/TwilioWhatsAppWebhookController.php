<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tag;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\ReplyIntent;
use App\Services\WhatsApp\TwilioWhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

/**
 * Twilio's two inbound webhooks for the WhatsApp confirmation flow.
 *
 *  - handleIncoming()  POST /webhooks/twilio/whatsapp         — customer replies
 *  - handleStatus()    POST /webhooks/twilio/whatsapp/status  — delivery/read receipts
 *
 * Both verify Twilio's `X-Twilio-Signature` HMAC against the stored auth token.
 * Unlike the iMile webhook (which has no signing spec at all), Twilio documents
 * this properly, so it is a real cryptographic check rather than a shared-secret
 * comparison.
 *
 * Both always answer 2xx once the signature passes — including for payloads we
 * can't match to an order. Twilio retries non-2xx responses, and there is
 * nothing to gain from having it redeliver a reply from a number we don't know.
 */
class TwilioWhatsAppWebhookController extends Controller
{
    public function __construct(private TwilioWhatsAppService $twilio) {}

    /**
     * Customer replied. Match the number back to the order we're chasing, log
     * the message, and act on what they said.
     */
    public function handleIncoming(Request $request): Response
    {
        if (! $this->verifySignature($request)) {
            return response('Invalid signature', 403);
        }

        $from = PhoneNumber::fromWhatsAppAddress($request->input('From'));
        $body = $request->input('Body');
        $sid = $request->input('MessageSid');

        Log::channel('whatsapp')->info('Inbound WhatsApp message', [
            'from' => $from,
            'sid' => $sid,
            'body' => $body,
        ]);

        if (! $from) {
            return response('', 204);
        }

        $order = $this->resolveOrder($from);

        if (! $order) {
            Log::channel('whatsapp')->info('Inbound WhatsApp message matched no order', ['from' => $from]);

            return response('', 204);
        }

        // Twilio can redeliver an inbound webhook; the unique twilio_sid keeps
        // that from duplicating the conversation history.
        WhatsAppMessage::updateOrCreate(
            ['twilio_sid' => $sid],
            [
                'order_id' => $order->id,
                'direction' => WhatsAppMessage::DIRECTION_INBOUND,
                'body' => $body,
                'to_number' => PhoneNumber::fromWhatsAppAddress($request->input('To')),
                'from_number' => $from,
                'status' => 'received',
            ]
        );

        $this->applyReply($order, $body);

        return response('', 204);
    }

    /**
     * Delivery lifecycle for a message we sent: queued → sent → delivered → read.
     *
     * ⚠️ `read` only ever arrives if the customer has read receipts enabled in
     * their WhatsApp privacy settings — a large share of users don't. Absence of
     * a read receipt therefore means "unknown", not "unread", and nothing in the
     * follow-up logic may treat it as proof the message went unseen.
     */
    public function handleStatus(Request $request): Response
    {
        if (! $this->verifySignature($request)) {
            return response('Invalid signature', 403);
        }

        $sid = $request->input('MessageSid') ?: $request->input('SmsSid');
        $status = $request->input('MessageStatus') ?: $request->input('SmsStatus');

        if (! $sid || ! $status) {
            return response('', 204);
        }

        $message = WhatsAppMessage::where('twilio_sid', $sid)->first();

        if (! $message) {
            return response('', 204);
        }

        $updates = ['status' => $status];

        match ($status) {
            'delivered' => $updates['delivered_at'] = $message->delivered_at ?? now(),
            'read' => $updates['read_at'] = $message->read_at ?? now(),
            'failed', 'undelivered' => $updates['failed_at'] = $message->failed_at ?? now(),
            default => null,
        };

        if ($code = $request->input('ErrorCode')) {
            $updates['error_code'] = $code;
            $updates['error_message'] = $request->input('ErrorMessage');
        }

        $message->update($updates);

        // Mirror the latest timestamps onto the order so the orders list can
        // show "delivered / read at" without joining the message log.
        if ($order = $message->order) {
            if ($status === 'delivered' && ! $order->whatsapp_delivered_at) {
                $order->forceFill(['whatsapp_delivered_at' => now()])->saveQuietly();
            }

            if ($status === 'read' && ! $order->whatsapp_read_at) {
                $order->forceFill(['whatsapp_read_at' => now()])->saveQuietly();
            }
        }

        Log::channel('whatsapp')->info('WhatsApp status callback', [
            'sid' => $sid,
            'status' => $status,
            'order_id' => $message->order_id,
            'error_code' => $request->input('ErrorCode'),
        ]);

        return response('', 204);
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

        Log::channel('whatsapp')->info('WhatsApp reply processed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'intent' => $intent,
        ]);
    }

    /**
     * Twilio signs the full request URL plus the sorted POST body with the
     * account auth token.
     */
    private function verifySignature(Request $request): bool
    {
        $token = $this->twilio->authToken();

        if (! $token) {
            Log::channel('whatsapp')->error('Twilio webhook rejected — auth token not configured');

            return false;
        }

        $signature = $request->header('X-Twilio-Signature');

        if (! $signature) {
            return false;
        }

        // Must be the URL exactly as Twilio built the signature over. Behind
        // ngrok/proxies Laravel already reconstructs this correctly because
        // AppServiceProvider forces the APP_URL scheme.
        return (new RequestValidator($token))->validate(
            $signature,
            $request->fullUrl(),
            $request->post()
        );
    }
}

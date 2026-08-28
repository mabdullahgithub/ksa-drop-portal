<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\WhatsApp\MetaWhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one stage of the WhatsApp confirmation conversation.
 *
 * Dispatched from two places: {@see \App\Observers\OrderObserver} the moment an
 * agent marks a call `no_answer`, and
 * {@see \App\Console\Commands\ProcessWhatsAppFollowUps} at the 24h mark.
 */
class SendWhatsAppOrderMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /** Exponential-ish backoff, in seconds, between the 3 attempts. */
    public array $backoff = [30, 120];

    public function __construct(
        private int $orderId,
        private string $templateKey = MetaWhatsAppService::TEMPLATE_ORDER_PENDING,
    ) {}

    public function handle(MetaWhatsAppService $whatsapp): void
    {
        $order = Order::withoutGlobalScope('shopify_visible')->with('client')->find($this->orderId);

        if (! $order) {
            return;
        }

        if (! $this->shouldSend($order)) {
            return;
        }

        $phone = PhoneNumber::toE164($order->customer_phone ?: $order->shipping_phone);

        if (! $phone) {
            Log::channel('whatsapp')->warning('Skipping WhatsApp send — no usable phone number', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'raw_phone' => $order->customer_phone,
            ]);

            $order->forceFill(['whatsapp_status' => Order::WHATSAPP_FAILED])->save();

            return;
        }

        // Persist the normalised number before sending: the inbound reply
        // webhook matches on it, and a reply can land before this job's own
        // post-send write commits.
        if ($order->whatsapp_phone_e164 !== $phone) {
            $order->forceFill(['whatsapp_phone_e164' => $phone])->save();
        }

        try {
            $whatsapp->sendTemplate($order, $this->templateKey, $this->variablesFor($order));
        } catch (Throwable $e) {
            Log::channel('whatsapp')->error('WhatsApp send failed', [
                'order_id' => $order->id,
                'template' => $this->templateKey,
                'error' => $e->getMessage(),
            ]);

            // Only give up once Laravel has exhausted the retries; a transient
            // Meta 5xx on attempt 1 shouldn't strand the order as failed.
            if ($this->attempts() >= $this->tries) {
                $order->forceFill(['whatsapp_status' => Order::WHATSAPP_FAILED])->save();
            }

            throw $e;
        }

        $order->forceFill($this->templateKey === MetaWhatsAppService::TEMPLATE_FOLLOWUP
            ? ['whatsapp_status' => Order::WHATSAPP_FOLLOWUP_SENT, 'whatsapp_followup_sent_at' => now()]
            : ['whatsapp_status' => Order::WHATSAPP_SENT, 'whatsapp_sent_at' => now()]
        )->save();

        Log::channel('whatsapp')->info('WhatsApp message sent', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'template' => $this->templateKey,
        ]);
    }

    /**
     * Idempotency guard. The scheduler runs every 15 minutes and a job can be
     * retried, so a stage must never be sent twice: only advance forwards, and
     * never message a customer who has already replied or been resolved.
     */
    private function shouldSend(Order $order): bool
    {
        // A reply, a confirmation, or a manual resolution ends the conversation.
        if (in_array($order->whatsapp_status, [Order::WHATSAPP_REPLIED, Order::WHATSAPP_CONFIRMED], true)) {
            return false;
        }

        // The call outcome may have changed between dispatch and execution —
        // an agent who reached the customer on a second attempt shouldn't
        // trigger a "we couldn't reach you" message.
        if ($order->call_status !== Order::CALL_NO_ANSWER) {
            return false;
        }

        if (in_array($order->fulfillment_status, ['fulfilled', 'cancelled'], true)) {
            return false;
        }

        return match ($this->templateKey) {
            MetaWhatsAppService::TEMPLATE_ORDER_PENDING => $order->whatsapp_status === null
                || $order->whatsapp_status === Order::WHATSAPP_FAILED,
            MetaWhatsAppService::TEMPLATE_FOLLOWUP => $order->whatsapp_status === Order::WHATSAPP_SENT,
            default => false,
        };
    }

    /**
     * Positional template body variables. Order matters and must match the
     * template approved in WhatsApp Manager — see MetaWhatsAppService::defaultBody().
     *
     * @return array<string,string>
     */
    private function variablesFor(Order $order): array
    {
        $address = collect([
            $order->shipping_address1 ?: $order->shipping_street,
            $order->shipping_city,
            $order->shipping_province,
        ])->filter()->implode(', ');

        // Three slots, and none of them is a brand name. See
        // MetaWhatsAppService::defaultBody() — the customer ordered from our
        // client's store, so naming the fulfilment platform would expose the
        // dropshipping relationship. There is deliberately no company-name
        // variable to fall back on.
        return [
            '1' => $order->customer_name ?: $order->shipping_name ?: 'there',
            '2' => $order->order_number,
            '3' => $address ?: 'not on file',
        ];
    }
}

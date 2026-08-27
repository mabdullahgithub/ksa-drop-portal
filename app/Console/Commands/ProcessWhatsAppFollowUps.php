<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppOrderMessageJob;
use App\Models\Order;
use App\Models\Tag;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\TwilioWhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Advances the WhatsApp confirmation conversation on its 24h clock.
 *
 *   sent          + 24h silence  → follow-up template
 *   followup_sent + 24h silence  → graveyard (ops stops chasing)
 *
 * Runs every 15 minutes; "24h" is therefore "at least 24h", never less.
 */
class ProcessWhatsAppFollowUps extends Command
{
    protected $signature = 'whatsapp:process-followups
                            {--hours=24 : Hours of silence before advancing a stage}
                            {--limit=200 : Maximum orders to process per stage}';

    protected $description = 'Send 24h WhatsApp follow-ups and graveyard orders that never replied';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $threshold = now()->subHours($hours);

        $followUps = $this->sendFollowUps($threshold, $limit);
        $buried = $this->buryUnanswered($threshold, $limit);

        $this->info("Done. Follow-ups queued: {$followUps}, moved to graveyard: {$buried}");

        return self::SUCCESS;
    }

    /**
     * Stage 1 → 2. Orders pinged 24h ago with no reply get one follow-up.
     */
    private function sendFollowUps(\Carbon\CarbonInterface $threshold, int $limit): int
    {
        $orders = Order::withoutGlobalScope('shopify_visible')
            ->where('whatsapp_status', Order::WHATSAPP_SENT)
            ->where('call_status', Order::CALL_NO_ANSWER)
            ->whereNull('whatsapp_replied_at')
            ->where('whatsapp_sent_at', '<=', $threshold)
            ->limit($limit)
            ->get();

        $queued = 0;

        foreach ($orders as $order) {
            // If the first message never landed — number not on WhatsApp — a
            // second template would fail identically and still be billed. Send
            // it back to the call queue instead.
            if ($this->initialSendFailed($order)) {
                $order->forceFill(['whatsapp_status' => Order::WHATSAPP_FAILED])->saveQuietly();
                $this->applyTag($order, 'WhatsApp Unreachable', '#ef4444', 'WhatsApp could not deliver — call again');

                Log::channel('whatsapp')->info('Skipping follow-up — initial message undeliverable', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);

                continue;
            }

            SendWhatsAppOrderMessageJob::dispatch($order->id, TwilioWhatsAppService::TEMPLATE_FOLLOWUP);
            $queued++;
        }

        return $queued;
    }

    /**
     * Stage 2 → done. 48h total silence: stop spending messages on this order.
     */
    private function buryUnanswered(\Carbon\CarbonInterface $threshold, int $limit): int
    {
        $orders = Order::withoutGlobalScope('shopify_visible')
            ->where('whatsapp_status', Order::WHATSAPP_FOLLOWUP_SENT)
            ->whereNull('whatsapp_replied_at')
            ->where('whatsapp_followup_sent_at', '<=', $threshold)
            ->limit($limit)
            ->get();

        foreach ($orders as $order) {
            $order->forceFill(['whatsapp_status' => Order::WHATSAPP_GRAVEYARD])->saveQuietly();
            $this->applyTag($order, 'Graveyard', '#6b7280', 'No response after two WhatsApp attempts');

            Log::channel('whatsapp')->info('Order moved to graveyard', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }

        return $orders->count();
    }

    /**
     * True when every outbound message on this order hit a terminal Twilio
     * failure. Requires status callbacks to be reaching us — if they aren't,
     * nothing is marked failed and the follow-up sends as normal.
     */
    private function initialSendFailed(Order $order): bool
    {
        $outbound = $order->whatsappMessages()
            ->where('direction', WhatsAppMessage::DIRECTION_OUTBOUND)
            ->get();

        return $outbound->isNotEmpty()
            && $outbound->every(fn (WhatsAppMessage $m) => $m->hasFailed());
    }

    /**
     * Swap the order's single tag, reusing the tag-catalog pattern from
     * PortalController::defaultOrderTags() so the label shows up in the
     * existing tag filters with no UI change.
     */
    private function applyTag(Order $order, string $name, string $color, string $description): void
    {
        Tag::firstOrCreate(['name' => $name], ['color' => $color, 'description' => $description]);

        $order->forceFill(['tags' => [$name]])->saveQuietly();
    }
}

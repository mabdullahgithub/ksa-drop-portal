<?php

namespace App\Observers;

use App\Jobs\SendWhatsAppOrderMessageJob;
use App\Models\Order;
use App\Observers\Concerns\RecordsDeletionAudit;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Support\Facades\Storage;

/**
 * Starts the WhatsApp confirmation conversation the moment an ops agent marks
 * a call as unanswered.
 *
 * Hooking the model rather than the controller means every path that sets
 * `call_status` — the single-order action, the bulk action, a future import,
 * tinker — triggers the flow identically, with no way to forget one.
 *
 * Also cleans up invoice files on permanent delete and records the recycle-bin
 * deletion audit trail (via {@see RecordsDeletionAudit}).
 */
class OrderObserver
{
    use RecordsDeletionAudit;

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('call_status')) {
            return;
        }

        if ($order->call_status === Order::CALL_NO_ANSWER) {
            $this->startConfirmationFlow($order);

            return;
        }

        // The agent reached the customer after all (or the order died on the
        // phone) — stop the sweep from chasing it with a follow-up.
        if (in_array($order->call_status, [Order::CALL_CONFIRMED, Order::CALL_CANCELLED, Order::CALL_WRONG_NUMBER], true)
            && in_array($order->whatsapp_status, [Order::WHATSAPP_SENT, Order::WHATSAPP_FOLLOWUP_SENT], true)) {
            $order->forceFill([
                'whatsapp_status' => $order->call_status === Order::CALL_CONFIRMED
                    ? Order::WHATSAPP_CONFIRMED
                    : Order::WHATSAPP_GRAVEYARD,
            ])->saveQuietly();
        }
    }

    private function startConfirmationFlow(Order $order): void
    {
        // Already messaged on a previous no-answer attempt — the existing
        // conversation and its 24h clock stand; don't restart it.
        if ($order->whatsapp_status !== null && $order->whatsapp_status !== Order::WHATSAPP_FAILED) {
            return;
        }

        if (in_array($order->fulfillment_status, ['fulfilled', 'cancelled'], true)) {
            return;
        }

        SendWhatsAppOrderMessageJob::dispatch($order->id, MetaWhatsAppService::TEMPLATE_ORDER_PENDING);
    }

    /**
     * invoices.order_id cascades on hard delete, so the invoice rows vanish
     * with the order and take their file_path with them. The generated PDFs on
     * the local disk would be orphaned, so collect the paths while the rows
     * still exist (forceDeleting, before the cascade) and unlink them.
     */
    public function forceDeleting(Order $order): void
    {
        $paths = $order->invoices()
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->all();

        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}

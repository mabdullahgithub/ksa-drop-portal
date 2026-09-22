<?php

namespace App\Observers;

use App\Models\Order;
use App\Observers\Concerns\RecordsDeletionAudit;
use Illuminate\Support\Facades\Storage;

class OrderObserver
{
    use RecordsDeletionAudit;

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

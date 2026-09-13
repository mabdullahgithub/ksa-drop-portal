<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One append-only entry in the stock ledger.
 *
 * Every change to a stock pool — automatic (a shipment leaving the warehouse
 * or coming back) and manual (an admin editing the quantity) — writes a row
 * here, so a pool's current value can always be explained.
 */
class StockMovement extends Model
{
    use HasFactory;

    public const REASON_SHIPMENT_DISPATCHED = 'shipment_dispatched';
    public const REASON_SHIPMENT_RETURNED   = 'shipment_returned';
    public const REASON_MANUAL_ADJUSTMENT   = 'manual_adjustment';

    protected $fillable = [
        'stockable_type',
        'stockable_id',
        'quantity',
        'balance_after',
        'reason',
        'note',
        'dedupe_key',
        'order_id',
        'order_item_id',
        'shipment_id',
        'user_id',
    ];

    protected $casts = [
        'quantity'      => 'integer',
        'balance_after' => 'integer',
    ];

    /**
     * Deterministic idempotency key for a movement caused by a shipment.
     *
     * Keyed per order line rather than per shipment so a partially applied
     * shipment (one line whose SKU we couldn't match yet) can be safely
     * retried later without the lines that already moved moving twice.
     */
    public static function dedupeKeyFor(int $shipmentId, string $reason, int $orderItemId): string
    {
        return "shipment:{$shipmentId}:{$reason}:{$orderItemId}";
    }

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

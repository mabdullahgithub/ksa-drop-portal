<?php

namespace App\Models;

use App\Models\Concerns\RestrictedByClientAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One parcel scanned OUT of or IN to the warehouse by an inventory manager —
 * see App\Services\Inventory\StockScanRecorder.
 */
class StockScan extends Model
{
    use RestrictedByClientAccess;

    protected static function clientAccessColumn(): string
    {
        return 'order_id';
    }
    public const OUT = 'out';

    public const IN = 'in';

    public const DIRECTIONS = [self::OUT, self::IN];

    protected $fillable = [
        'shipment_id',
        'order_id',
        'direction',
        'rider_id',
        'parcel_rider_id',
        'pieces',
        'entry_method',
        'occurred_at',
        'client_uuid',
    ];

    protected $casts = [
        'pieces' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The inventory manager who scanned it.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class)->withTrashed();
    }

    /**
     * The rider who held the parcel when it was scanned.
     */
    public function parcelRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'parcel_rider_id')->withTrashed();
    }

    /**
     * The parcel's items, and what this scan did to the stock of each.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockScanItem::class)->orderBy('id');
    }
}

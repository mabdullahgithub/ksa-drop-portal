<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What KSA Drop owes a rider for one visit — see App\Services\Riders\RiderPay.
 */
class RiderEarning extends Model
{
    public const TYPE_DELIVERED = 'delivered';

    public const TYPE_ATTEMPTED = 'attempted';

    protected $fillable = [
        'rider_id',
        'shipment_id',
        'shipment_event_id',
        'type',
        'amount',
        'earned_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'earned_at' => 'datetime',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class)->withTrashed();
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}

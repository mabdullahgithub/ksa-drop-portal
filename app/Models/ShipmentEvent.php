<?php

namespace App\Models;

use App\Models\Concerns\RestrictedByClientAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One status update recorded by our own people — see
 * App\Services\Shipping\ShipmentEventRecorder.
 */
class ShipmentEvent extends Model
{
    use RestrictedByClientAccess;

    protected static function clientAccessColumn(): string
    {
        return 'shipment_id';
    }
    protected $fillable = [
        'shipment_id',
        'rider_id',
        'user_id',
        'action',
        'status_before',
        'status_after',
        'reason',
        'reschedule_date',
        'note',
        'cod_amount',
        'payment_method',
        'recipient_name',
        'photo_path',
        'lat',
        'lng',
        'accuracy_m',
        'entry_method',
        'occurred_at',
        'client_uuid',
    ];

    protected $casts = [
        'reschedule_date' => 'date:Y-m-d',
        'cod_amount' => 'decimal:2',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'occurred_at' => 'datetime',
    ];

    protected $hidden = [
        'photo_path',
    ];

    protected $appends = [
        'has_photo',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getHasPhotoAttribute(): bool
    {
        return $this->photo_path !== null;
    }
}

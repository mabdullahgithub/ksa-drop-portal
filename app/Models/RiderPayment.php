<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money between a rider and KSA Drop, in either direction: COD cash the
 * rider hands in (App\Services\Riders\RiderCash), or KSA Drop paying the
 * rider what they earned (App\Services\Riders\RiderPay). The two are kept
 * apart, never netted. Never edited or deleted: a wrong entry is voided and
 * entered again.
 */
class RiderPayment extends Model
{
    /** The rider handed COD cash in to KSA Drop. */
    public const DIRECTION_IN = 'in';

    /** KSA Drop paid the rider. */
    public const DIRECTION_OUT = 'out';

    public const DIRECTIONS = [self::DIRECTION_IN, self::DIRECTION_OUT];

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_OTHER = 'other';

    public const METHODS = [self::METHOD_CASH, self::METHOD_BANK_TRANSFER, self::METHOD_OTHER];

    /**
     * Which COD a payment from the rider settles, by how the customer paid
     * it (shipment_events.payment_method). Each is owed on its own.
     */
    public const COD_METHODS = ['cash', 'card', 'transfer'];

    protected $fillable = [
        'rider_id',
        'direction',
        'amount',
        'method',
        'cod_method',
        'pay_source',
        'reference',
        'note',
        'received_at',
        'for_date',
        'recorded_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'client_uuid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
        'for_date' => 'date:Y-m-d',
        'voided_at' => 'datetime',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class)->withTrashed();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /**
     * Payments that count: not voided.
     */
    public function scopeCounted($query)
    {
        return $query->whereNull('voided_at');
    }

    public function scopeHandedIn($query)
    {
        return $query->where('direction', self::DIRECTION_IN);
    }

    public function scopePaidOut($query)
    {
        return $query->where('direction', self::DIRECTION_OUT);
    }

    /**
     * Payments recorded for one KSA day (`Y-m-d`); all of them when null.
     */
    public function scopeForDay($query, ?string $day)
    {
        return $day === null ? $query : $query->whereDate('for_date', $day);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A partner asking to redeem points. Points leave the balance when the
 * request is approved and come back if it is rejected after that; the
 * transfer itself is made by a person, who records it here with proof.
 */
class PartnerPayoutRequest extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_REQUESTED, self::STATUS_APPROVED, self::STATUS_PAID, self::STATUS_REJECTED];

    protected $fillable = [
        'partner_id',
        'points',
        'status',
        'payout_method',
        'partner_note',
        'admin_note',
        'amount_paid',
        'currency',
        'paid_at',
        'handled_by',
    ];

    protected $casts = [
        'points' => 'integer',
        'payout_method' => 'encrypted:array',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected $hidden = ['proof_path'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
    }
}

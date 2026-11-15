<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a partner's points ledger. The balance on the partner row is
 * the sum of these; App\Services\Partners\PartnerPoints keeps it in step.
 */
class PartnerPointTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_APPROVAL_BONUS = 'approval_bonus';

    public const TYPE_ACTIVATION = 'activation';

    public const TYPE_DELIVERY = 'delivery';

    public const TYPE_MILESTONE = 'milestone';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_PAYOUT = 'payout';

    public const TYPE_PAYOUT_REVERSAL = 'payout_reversal';

    protected $fillable = [
        'partner_id',
        'type',
        'points',
        'reference_type',
        'reference_id',
        'client_id',
        'description',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'created_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}

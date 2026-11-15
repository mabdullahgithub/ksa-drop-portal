<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller a partner sent to KSA Drop, before (or without) an account: the
 * form on the partner's referral page, or a name the partner typed in.
 * When the team opens the seller's account, the lead is marked converted and
 * the client carries the partner.
 */
class PartnerLead extends Model
{
    public const STATUSES = ['new', 'contacted', 'converted', 'lost'];

    protected $fillable = [
        'partner_id',
        'name',
        'company',
        'phone',
        'email',
        'store_url',
        'monthly_orders',
        'source',
        'status',
        'client_id',
        'message',
        'notes',
        'ip',
    ];

    protected $hidden = ['ip'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

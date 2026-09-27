<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderActivation extends Model
{
    protected $fillable = [
        'rider_id',
        'token_hash',
        'expires_at',
        'claimed_at',
        'voided_at',
        'rider_device_id',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function isUsable(): bool
    {
        return $this->claimed_at === null
            && $this->voided_at === null
            && $this->expires_at->isFuture();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\RestrictedByClientAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPayment extends Model
{
    use RestrictedByClientAccess;

    protected $fillable = [
        'client_id',
        'amount',
        'message',
        'proof_path',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected $appends = ['proof_url'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function getProofUrlAttribute(): ?string
    {
        return $this->proof_path ? asset($this->proof_path) : null;
    }
}

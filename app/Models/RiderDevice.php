<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderDevice extends Model
{
    public const REVOKED_REPLACED = 'replaced';

    public const REVOKED_BY_ADMIN = 'admin';

    public const REVOKED_RIDER_DELETED = 'rider_deleted';

    /** The rider tapped Log out in the app. */
    public const REVOKED_BY_RIDER = 'rider';

    protected $fillable = [
        'rider_id',
        'token_hash',
        'sign_in_method',
        'platform',
        'standalone',
        'user_agent',
        'last_ip',
        'last_seen_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected $casts = [
        'standalone' => 'boolean',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class)->withTrashed();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function revoke(string $reason): void
    {
        if ($this->revoked_at === null) {
            $this->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
        }
    }
}

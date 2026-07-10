<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientShopifyConnection extends Model
{
    protected $fillable = [
        'client_id',
        'shop_domain',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'refresh_token_expires_at',
        'scope',
        'sync_mode',
        'sync_filters',
        'status',
        'webhooks_registered',
        'last_synced_at',
        'connected_at',
    ];

    protected $casts = [
        'access_token'             => 'encrypted',   // Laravel AES-256 at rest
        'refresh_token'            => 'encrypted',
        'token_expires_at'         => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'sync_filters'             => 'array',
        'webhooks_registered'      => 'boolean',
        'last_synced_at'           => 'datetime',
        'connected_at'             => 'datetime',
    ];

    // Never expose tokens in JSON responses.
    protected $hidden = ['access_token', 'refresh_token'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Whether the access token is expired (or within a 5-minute safety buffer).
     * If we never stored an expiry (legacy non-expiring token), treat as valid.
     */
    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Whether the refresh token itself has expired (client must reconnect).
     */
    public function isRefreshTokenExpired(): bool
    {
        if (! $this->refresh_token_expires_at) {
            return false;
        }

        return $this->refresh_token_expires_at->isPast();
    }
}

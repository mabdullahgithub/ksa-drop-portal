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
        'fulfillment_service_id',
        'fulfillment_service_handle',
        'fulfillment_location_id',
        'fulfillment_registered_at',
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
        'fulfillment_registered_at' => 'datetime',
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

    /**
     * Whether this grant covers the fulfillment work (App Store requirement
     * 5.5.1). The fulfillment scopes were added after the app shipped, so a
     * connection made before that holds a token that predates them — every
     * fulfillment call on it would 403.
     *
     * Managed installation re-prompts the merchant on their next app load, and
     * the stored `scope` is refreshed from the new grant at that point. Until
     * then this returns false and the fulfillment paths skip the store quietly
     * rather than filling the log with permission errors nobody can act on.
     *
     * Two scopes are checked, each because the flow cannot work without it and
     * each one arrived in a separate release:
     *
     *   write_third_party_fulfillment_orders  what fulfillmentOrderSubmitFulfillmentRequest
     *                                         itself requires.
     *   read_locations                        every Location field — the service's own
     *                                         location, and the location a fulfillment
     *                                         order is assigned to. Without it the
     *                                         registration lookup is refused outright,
     *                                         and so is the check that keeps us off
     *                                         other suppliers' fulfillment orders.
     *
     * Checking both matters beyond correctness: this is also what triggers the
     * grant refresh on app load (ShopifyService::ensureInstalled). A store that
     * approved the first release's scopes but not read_locations must still
     * read as not ready, or it would never be re-exchanged to pick it up.
     */
    public const REQUIRED_FULFILLMENT_SCOPES = [
        'write_third_party_fulfillment_orders',
        'read_locations',
    ];

    public function hasFulfillmentScopes(): bool
    {
        $granted = array_map('trim', explode(',', (string) $this->scope));

        return array_diff(self::REQUIRED_FULFILLMENT_SCOPES, $granted) === [];
    }

    /**
     * Registered as a fulfillment service on the merchant's store, so Shopify
     * has a KSADrop location to assign fulfillment orders to.
     */
    public function hasFulfillmentService(): bool
    {
        return filled($this->fulfillment_service_id) && filled($this->fulfillment_location_id);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Shopify webhook delivery that failed to become an order, parked with the
 * payload that produced it so it can be replayed.
 *
 * Only genuine failures land here — a delivery we accepted and could not
 * process. A webhook from a store that has not been connected to a KSA Drop
 * account is not a failure and is not held: syncing begins when the merchant
 * connects, and orders placed before that stay in Shopify.
 *
 * @see \App\Jobs\ProcessShopifyWebhookJob  records and resolves these
 * @see \App\Console\Commands\RetryShopifySyncFailures  drains them on a schedule
 */
class ShopifySyncFailure extends Model
{
    /** The sync itself threw — mapping, validation or database error. */
    public const REASON_EXCEPTION = 'exception';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RESOLVED  = 'resolved';
    public const STATUS_ABANDONED = 'abandoned';

    /**
     * Minutes to wait before attempt N+1, indexed by attempts already made.
     * Front-loaded because most failures are transient (a deadlock, a brief
     * database blip, a store claimed a minute later), then stretched out so a
     * genuinely broken payload stops costing a queue slot every few minutes.
     * Runs ~2 days end to end, which comfortably outlasts Shopify's own ~48h
     * retry window — past that a human has to look at it.
     */
    private const BACKOFF_MINUTES = [1, 5, 15, 60, 180, 360, 720, 1440];

    public const MAX_ATTEMPTS = 8;

    protected $fillable = [
        'shop_domain',
        'topic',
        'shopify_order_id',
        'order_number',
        'client_id',
        'payload',
        'reason',
        'error_message',
        'status',
        'attempts',
        'next_attempt_at',
        'last_attempted_at',
        'resolved_at',
    ];

    protected $casts = [
        'payload'           => 'array',
        'attempts'          => 'integer',
        'next_attempt_at'   => 'datetime',
        'last_attempted_at' => 'datetime',
        'resolved_at'       => 'datetime',
    ];

    // The raw webhook body carries customer PII and is only ever needed for a
    // replay — never ship it to the browser with the row.
    protected $hidden = ['payload'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Park a failed delivery, or re-open the existing row for the same order.
     *
     * De-duplication is per order, not per delivery: an order that fails on
     * orders/create and again on orders/updated is one stuck order. Topics with
     * no order attached (GDPR redactions) collapse on (shop, topic) instead.
     */
    public static function record(
        string $shopDomain,
        string $topic,
        array $payload,
        string $reason,
        ?string $errorMessage = null,
        ?int $clientId = null,
    ): self {
        $shopifyOrderId = isset($payload['id']) ? (string) $payload['id'] : null;

        $key = $shopifyOrderId !== null
            ? ['shop_domain' => $shopDomain, 'shopify_order_id' => $shopifyOrderId]
            : ['shop_domain' => $shopDomain, 'shopify_order_id' => null, 'topic' => $topic];

        try {
            return static::write($key, $topic, $payload, $reason, $errorMessage, $clientId);
        } catch (UniqueConstraintViolationException) {
            // Two deliveries for one order can fail at the same moment and both
            // try to park it. The unique key means one of them loses the insert
            // — it folds into the row the winner just created rather than
            // bubbling a duplicate-key error out of a failure handler.
            return static::write($key, $topic, $payload, $reason, $errorMessage, $clientId);
        }
    }

    /**
     * @param  array<string,mixed>  $key  the row's identity (see record())
     */
    private static function write(
        array $key,
        string $topic,
        array $payload,
        string $reason,
        ?string $errorMessage,
        ?int $clientId,
    ): self {
        $failure = static::firstOrNew($key);

        // A row that had already been given up on, or one already resolved, is
        // reopened from scratch — this is a fresh failure, and it deserves the
        // full retry budget rather than inheriting a spent one.
        $isReopening = ! $failure->exists || $failure->status !== self::STATUS_PENDING;

        $failure->fill([
            'topic'         => $topic,
            'payload'       => $payload,
            'reason'        => $reason,
            'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 2000) : null,
            'order_number'  => static::orderNumberFrom($payload) ?? $failure->order_number,
            // Never unset a client we had already attributed the failure to.
            'client_id'     => $clientId ?? $failure->client_id,
            'status'        => self::STATUS_PENDING,
            'resolved_at'   => null,
        ]);

        if ($isReopening) {
            $failure->attempts        = 0;
            $failure->next_attempt_at = now()->addMinutes(self::BACKOFF_MINUTES[0]);
        }

        $failure->save();

        return $failure;
    }

    /**
     * Mark every pending failure for this order as resolved.
     *
     * Called on any successful sync of the order — a replay that worked, or
     * simply a later orders/updated webhook that got through on its own. Either
     * way the order is in the portal and there is nothing left to retry.
     */
    public static function resolveFor(string $shopDomain, ?string $shopifyOrderId): int
    {
        if ($shopifyOrderId === null || $shopifyOrderId === '') {
            return 0;
        }

        return static::where('shop_domain', $shopDomain)
            ->where('shopify_order_id', $shopifyOrderId)
            ->where('status', self::STATUS_PENDING)
            ->update([
                'status'      => self::STATUS_RESOLVED,
                'resolved_at' => now(),
                'updated_at'  => now(),
            ]);
    }

    /**
     * Failures whose next attempt is due (a null next_attempt_at means "now",
     * which is what a manual retry from the portal sets).
     */
    public function scopeDue(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->where('status', self::STATUS_PENDING)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(fn (Builder $q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $at));
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_ABANDONED]);
    }

    /**
     * Count an attempt and schedule the next one, giving up once the backoff
     * schedule is exhausted. Called before the replay runs, not after: if the
     * replay crashes the worker outright, the attempt still has to count or the
     * row would be retried forever.
     */
    public function registerAttempt(): void
    {
        $attempts = $this->attempts + 1;

        $this->forceFill([
            'attempts'          => $attempts,
            'last_attempted_at' => now(),
            'status'            => $attempts >= self::MAX_ATTEMPTS ? self::STATUS_ABANDONED : $this->status,
            'next_attempt_at'   => $attempts >= self::MAX_ATTEMPTS
                ? null
                : now()->addMinutes(self::BACKOFF_MINUTES[$attempts]),
        ])->save();
    }

    /**
     * Restore the full retry budget and spend one attempt on it, for callers
     * that dispatch the replay themselves right now — the portal's manual
     * "Retry" and the replay fired when a store is connected. Both mean the
     * merchant just changed something that may have fixed the cause, so even an
     * abandoned failure is worth a fresh run.
     *
     * Spending the attempt here is what keeps the scheduled sweep from picking
     * the same row up seconds later and dispatching it a second time.
     */
    public function beginImmediateReplay(?int $clientId = null): void
    {
        $this->forceFill([
            'status'      => self::STATUS_PENDING,
            'attempts'    => 0,
            'resolved_at' => null,
            'client_id'   => $clientId ?? $this->client_id,
        ])->save();

        $this->registerAttempt();
    }

    /**
     * Shopify's human-facing order number, for display in the portal.
     */
    private static function orderNumberFrom(array $payload): ?string
    {
        $number = $payload['name'] ?? $payload['order_number'] ?? null;

        return $number !== null ? (string) $number : null;
    }
}

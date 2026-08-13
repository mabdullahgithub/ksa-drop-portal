<?php

namespace App\Models;

use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'courier',
        'tracking_number',
        'txlogistic_id',
        'sorting_code',
        'status',
        'courier_status',
        'courier_status_description',
        'tracking_history',
        'api_response',
        'label_url',
        'weight',
        'length',
        'width',
        'height',
        'service_type',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
        'error_message',
        'exception_note',
        'exception_escalated_at',
        'otp_verified',
        'otp_verified_at',
        'return_tracking_number',
    ];

    protected $casts = [
        'tracking_history'        => 'array',
        'api_response'            => 'array',
        'weight'                  => 'decimal:2',
        'length'                  => 'decimal:2',
        'width'                   => 'decimal:2',
        'height'                  => 'decimal:2',
        'shipped_at'              => 'datetime',
        'delivered_at'            => 'datetime',
        'cancelled_at'            => 'datetime',
        'exception_escalated_at'  => 'datetime',
        'otp_verified'            => 'boolean',
        'otp_verified_at'         => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'status_color',
    ];

    protected $hidden = [
        'api_response',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            ShipmentStatus::DELIVERED->value,
            ShipmentStatus::CANCELLED->value,
            ShipmentStatus::FAILED->value,
            ShipmentStatus::RETURNED->value,
        ]);
    }

    public function scopeByCourier($query, string $courier)
    {
        return $query->where('courier', $courier);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeTrackable($query)
    {
        return $query->whereIn('status', [
            ShipmentStatus::INFO_RECEIVED->value,
            ShipmentStatus::IN_TRANSIT->value,
            ShipmentStatus::OUT_FOR_DELIVERY->value,
            ShipmentStatus::ATTEMPT_FAIL->value,
            // Exception isn't a dead end — without polling it, a shipment
            // that hits Exception could only ever move again via another
            // webhook push (see ShipmentStatus::shouldTransitionTo()).
            ShipmentStatus::EXCEPTION->value,
        ]);
    }

    public function getStatusEnumAttribute(): ShipmentStatus
    {
        return ShipmentStatus::tryFrom($this->status) ?? ShipmentStatus::PENDING;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status_enum->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status_enum->color();
    }

    public function getIsTrackableAttribute(): bool
    {
        return $this->tracking_number && $this->status_enum->isActive();
    }

    public function addTrackingEvent(TrackingEvent $event): void
    {
        $this->addTrackingEvents([$event]);
    }

    /**
     * Resolve what a tracking update should actually do to this shipment's
     * status, honoring {@see ShipmentStatus::shouldTransitionTo()}'s
     * ordering guard so an out-of-order courier event can't move the
     * status backward.
     *
     * Also detects "recovery": a shipment that was Exception and is now
     * moving again. `escalateException()`/`escalateOnce()` only notify
     * admins once per shipment (via `exception_escalated_at`) so repeated
     * pushes about the *same* incident don't spam them — but without
     * clearing that flag on recovery, a later, unrelated exception would
     * silently fail to re-notify anyone, since the flag from the first,
     * already-resolved incident is still sitting there. `exception_note`
     * is left untouched as a record of the last incident; only the
     * escalation flag resets, so the next real exception starts fresh.
     *
     * Shared by both webhook handlers, the polling sync command, and the
     * on-demand track() refresh — every place a courier's reported status
     * gets applied to a shipment.
     *
     * @return array{0: ShipmentStatus, 1: array<string, mixed>} [status to apply, extra attributes to persist alongside it]
     */
    public function resolveTrackingStatus(ShipmentStatus $incoming): array
    {
        $current = $this->status_enum;
        $applied = $current->shouldTransitionTo($incoming) ? $incoming : $current;

        $extra = [];

        if ($current === ShipmentStatus::EXCEPTION
            && $applied !== ShipmentStatus::EXCEPTION
            && $this->exception_escalated_at !== null) {
            $extra['exception_escalated_at'] = null;
        }

        return [$applied, $extra];
    }

    /**
     * Merge one or more scan events into the shipment's tracking history.
     *
     * Events already present (same timestamp + raw status + scan network) are
     * skipped so repeated J&T webhook pushes are idempotent, and the resulting
     * history is kept newest-first by scan time.
     *
     * @param  array<int, TrackingEvent>  $events
     */
    public function addTrackingEvents(array $events): void
    {
        $history = $this->tracking_history ?? [];

        $seen = [];
        foreach ($history as $existing) {
            $seen[$this->trackingEventSignature($existing)] = true;
        }

        foreach ($events as $event) {
            $entry = $event->toArray();
            $signature = $this->trackingEventSignature($entry);

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $history[] = $entry;
        }

        // Newest first. J&T scan times ("Y-m-d H:i:s") and ISO-8601 timestamps
        // both sort lexicographically by their leading date, so a string
        // comparison orders them correctly.
        usort($history, fn ($a, $b) => strcmp(
            (string) ($b['timestamp'] ?? ''),
            (string) ($a['timestamp'] ?? ''),
        ));

        $this->tracking_history = $history;
    }

    protected function trackingEventSignature(array $event): string
    {
        return implode('|', [
            $event['timestamp'] ?? '',
            $event['raw_status'] ?? '',
            $event['network_id'] ?? '',
        ]);
    }

    public function markDelivered(): void
    {
        // Avoid duplicate side-effects if already delivered.
        $alreadyDelivered = $this->status === ShipmentStatus::DELIVERED->value && $this->delivered_at;

        $this->update([
            'status' => ShipmentStatus::DELIVERED->value,
            'delivered_at' => $this->delivered_at ?? now(),
        ]);

        if ($this->order) {
            $this->order->update([
                'fulfillment_status' => 'fulfilled',
                'fulfilled_at' => $this->order->fulfilled_at ?? now(),
            ]);
        }

        if (! $alreadyDelivered) {
            $this->notifyAdminsOfDelivery();
        }
    }

    protected function notifyAdminsOfDelivery(): void
    {
        try {
            $this->loadMissing('order');

            \App\Models\User::role(['admin', 'superadmin'])->each(
                fn ($admin) => $admin->notify(new \App\Notifications\ShipmentDeliveredNotification($this))
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ShipmentDeliveredNotification failed for shipment ' . $this->id . ': ' . $e->getMessage());
        }
    }

    public function markReturned(string $reason = ''): void
    {
        $this->update([
            'status'        => ShipmentStatus::RETURNED->value,
            'cancel_reason' => $reason,
            'cancelled_at'  => now(),
        ]);

        if ($this->order) {
            $this->order->update([
                'fulfillment_status' => 'cancelled',
                'cancelled_at'       => $this->order->cancelled_at ?? now(),
            ]);
        }
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => ShipmentStatus::FAILED->value,
            'error_message' => $message,
        ]);
    }

    public function markCancelled(string $reason): void
    {
        $this->update([
            'status' => ShipmentStatus::CANCELLED->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    public function markOtpVerified(): void
    {
        $this->update([
            'otp_verified'    => true,
            'otp_verified_at' => $this->otp_verified_at ?? now(),
        ]);
    }

    public function escalateException(string $note = ''): void
    {
        $this->update([
            'exception_note'           => $note ?: $this->exception_note,
            'exception_escalated_at'   => now(),
        ]);

        try {
            $this->loadMissing('order');

            \App\Models\User::role(['admin', 'superadmin'])->each(
                fn ($admin) => $admin->notify(new \App\Notifications\ShipmentExceptionNotification($this))
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ShipmentExceptionNotification failed for shipment ' . $this->id . ': ' . $e->getMessage());
        }
    }

    public function scopeExceptions($query)
    {
        return $query->where('status', ShipmentStatus::EXCEPTION->value);
    }

    public function scopeEscalated($query)
    {
        return $query->whereNotNull('exception_escalated_at');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', ShipmentStatus::RETURNED->value);
    }
}

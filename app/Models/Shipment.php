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
    ];

    protected $casts = [
        'tracking_history' => 'array',
        'api_response' => 'array',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
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
        $history = $this->tracking_history ?? [];
        array_unshift($history, $event->toArray());
        $this->tracking_history = $history;
    }

    public function markDelivered(): void
    {
        // Avoid duplicate side-effects if already delivered.
        $alreadyDelivered = $this->status === ShipmentStatus::DELIVERED->value && $this->delivered_at;

        $this->update([
            'status' => ShipmentStatus::DELIVERED->value,
            'delivered_at' => $this->delivered_at ?? now(),
        ]);

        $this->order->update([
            'fulfillment_status' => 'fulfilled',
            'fulfilled_at' => $this->order->fulfilled_at ?? now(),
        ]);

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
}

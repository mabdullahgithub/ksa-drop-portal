<?php

namespace App\Models;

use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A KSA Express rider. Signs in to the rider app only — see
 * App\Services\Riders\RiderAuthService.
 */
class Rider extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** Consecutive wrong PINs before PIN sign-in is locked until the admin sets a new one. */
    public const MAX_PIN_ATTEMPTS = 10;

    protected $fillable = [
        'name',
        'phone',
        'name_ar',
        'national_id',
        'nationality',
        'vehicle_type',
        'vehicle_plate',
        'license_number',
        'license_expiry',
        'warehouse_id',
        'city',
        'employment_type',
        'iban',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'national_id' => 'encrypted',
        'iban' => 'encrypted',
        'license_expiry' => 'date',
        'pin_set_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'pin',
        'photo_path',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(RiderDevice::class);
    }

    public function activeDevice(): HasOne
    {
        return $this->hasOne(RiderDevice::class)->whereNull('revoked_at')->latestOfMany();
    }

    public function activations(): HasMany
    {
        return $this->hasMany(RiderActivation::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class);
    }

    /**
     * Who removed this rider. Null once restored.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    /**
     * Parcels this rider is still responsible for: marked out for delivery
     * (or failed and not yet handed back) and not finished.
     */
    public function heldShipments(): HasMany
    {
        return $this->shipments()->whereIn('status', [
            ShipmentStatus::OUT_FOR_DELIVERY->value,
            ShipmentStatus::ATTEMPT_FAIL->value,
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasPin(): bool
    {
        return $this->pin !== null;
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
    }

    public function isPinLocked(): bool
    {
        return $this->pin_failed_attempts >= self::MAX_PIN_ATTEMPTS;
    }

    /**
     * First name only — what the public tracking page shows ("Out for
     * delivery with Ahmed"). The full name and phone stay internal.
     */
    public function publicName(): string
    {
        return strtok(trim($this->name), ' ') ?: $this->name;
    }
}

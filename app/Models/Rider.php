<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A KSA Express rider. Signs in to the rider app only — see
 * App\Services\Riders\RiderAuthService.
 *
 * An inventory manager is one of these too, with a different role: same
 * sign-in, but their app only scans parcels OUT of and IN to the warehouse
 * (App\Services\Inventory\StockScanRecorder) and they never hold a parcel.
 */
class Rider extends Model
{
    use SoftDeletes;

    public const ROLE_RIDER = 'rider';

    public const ROLE_INVENTORY_MANAGER = 'inventory_manager';

    public const ROLES = [self::ROLE_RIDER, self::ROLE_INVENTORY_MANAGER];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** Consecutive wrong PINs before PIN sign-in is locked until the admin sets a new one. */
    public const MAX_PIN_ATTEMPTS = 10;

    protected $fillable = [
        'name',
        'phone',
        'role',
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
        'delivery_rate',
        'attempt_rate',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'status',
        'created_by',
    ];

    protected $attributes = [
        'role' => self::ROLE_RIDER,
    ];

    protected $casts = [
        'national_id' => 'encrypted',
        'iban' => 'encrypted',
        'delivery_rate' => 'decimal:2',
        'attempt_rate' => 'decimal:2',
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
     * Parcels this rider is still responsible for: out for delivery, failed
     * and not yet tried again, or returned/cancelled and not handed back.
     */
    public function heldShipments(): HasMany
    {
        return $this->shipments()->inRiderHands();
    }

    /**
     * Money between the rider and KSA Drop, both ways, voided entries included.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(RiderPayment::class);
    }

    /**
     * Parcels this inventory manager scanned OUT or IN at the warehouse.
     */
    public function stockScans(): HasMany
    {
        return $this->hasMany(StockScan::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInventoryManager(): bool
    {
        return $this->role === self::ROLE_INVENTORY_MANAGER;
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

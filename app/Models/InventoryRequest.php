<?php

namespace App\Models;

use App\Models\Concerns\RestrictedByClientAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A change a client asked for on one of their verified products — see
 * App\Services\Inventory\InventoryRequestRecorder (how one is made) and
 * InventoryRequestReviewer (how one is decided).
 */
class InventoryRequest extends Model
{
    use RestrictedByClientAccess;

    public const STOCK = 'stock';

    public const DETAILS = 'details';

    public const TYPES = [self::STOCK, self::DETAILS];

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PENDING, self::APPROVED, self::REJECTED, self::CANCELLED];

    /** The product fields a details request may change. */
    public const DETAIL_FIELDS = ['name', 'sku', 'description', 'unit_price', 'notes'];

    protected $fillable = [
        'client_id',
        'client_product_id',
        'type',
        'status',
        'revision',
        'requested_quantity',
        'stock_at_request',
        'received_quantity',
        'stock_before',
        'stock_after',
        'changes',
        'original',
        'rejection_reason',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'revision' => 'integer',
        'requested_quantity' => 'integer',
        'stock_at_request' => 'integer',
        'received_quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'changes' => 'array',
        'original' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClientProduct::class, 'client_product_id');
    }

    /**
     * Images the client added in a details request.
     */
    public function images(): HasMany
    {
        return $this->hasMany(InventoryRequestImage::class)->orderBy('position');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    /**
     * Requests the team can still act on: the product and its client are
     * both there. A client in the recycle bin keeps its products, so the
     * client is checked on its own.
     */
    public function scopeActionable(Builder $query): Builder
    {
        return $query->whereHas('product')->whereHas('client');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * What the client asked stock to change by: units above zero are coming
     * in, below zero are to be taken off.
     */
    public function requestedChange(): int
    {
        return (int) $this->requested_quantity - (int) $this->stock_at_request;
    }

    /**
     * End a pending request without a decision: the client withdrew it, or
     * the product it was about is gone or no longer verified.
     */
    public function cancel(): void
    {
        $this->deleteImages();
        $this->update(['status' => self::CANCELLED]);
    }

    /**
     * Drop the images the client proposed, files and rows.
     */
    public function deleteImages(): void
    {
        Storage::disk('public')->deleteDirectory(self::imageDirectory($this->id));
        $this->images()->delete();
    }

    public static function imageDirectory(int $id): string
    {
        return "client-product-requests/{$id}";
    }
}

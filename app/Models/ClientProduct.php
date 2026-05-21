<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'product_code',
        'name',
        'sku',
        'description',
        'quantity',
        'unit_price',
        'verification_status',
        'verified_at',
        'verified_by',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('product_code', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function getFormattedPriceAttribute(): ?string
    {
        if ($this->unit_price === null) {
            return null;
        }
        return 'SAR ' . number_format($this->unit_price, 2);
    }

    public static function generateProductCode(Client $client): string
    {
        $lastProduct = static::where('client_id', $client->id)
            ->orderByRaw("CAST(SUBSTRING(product_code, ?) AS UNSIGNED) DESC", [strlen($client->client_id) + 2])
            ->first();

        if ($lastProduct) {
            $lastNumber = (int) str_replace($client->client_id . '-', '', $lastProduct->product_code);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $client->client_id . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}

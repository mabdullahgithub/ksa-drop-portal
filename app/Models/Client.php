<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'created_by',
        'client_types',
        'company_name',
        'logo',
        'short_id',
        'contact_person',
        'phone',
        'secondary_phone',
        'address',
        'city',
        'country',
        'postal_code',
        'tax_id',
        'commercial_registration',
        'status',
        'portal_features',
        'charges',
        'notes',
    ];

    protected $casts = [
        'client_types' => 'array',
        'portal_features' => 'array',
        'charges' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shopifyConnection(): HasOne
    {
        return $this->hasOne(ClientShopifyConnection::class);
    }

    public function clientProducts(): HasMany
    {
        return $this->hasMany(ClientProduct::class);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('company_name', 'like', "%{$term}%")
              ->orWhere('contact_person', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('short_id', 'like', "%{$term}%")
              ->orWhereHas('user', function ($uq) use ($term) {
                  $uq->where('email', 'like', "%{$term}%")
                      ->orWhere('name', 'like', "%{$term}%");
              });
        });
    }

    public function scopeWithType($query, string $type)
    {
        return $query->whereJsonContains('client_types', $type);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function getClientIdAttribute(): string
    {
        return $this->short_id;
    }

    public function getIsDropshipperAttribute(): bool
    {
        return in_array('dropshipper', $this->client_types ?? []);
    }

    public function getIsFulfilmentAttribute(): bool
    {
        return in_array('fulfilment', $this->client_types ?? []);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'warning',
            'suspended' => 'error',
            default => 'default',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        $types = $this->client_types ?? [];
        return implode(' & ', array_map('ucfirst', $types));
    }

    public static function generateClientId(string $companyName): string
    {
        $words = array_filter(preg_split('/\s+/', trim($companyName)));
        $words = array_values($words);
        $wordCount = count($words);

        $candidates = [];

        if ($wordCount === 1) {
            $clean = preg_replace('/[^A-Z]/', '', strtoupper($words[0]));
            $clean = str_pad($clean, 4, 'X');
            $candidates[] = substr($clean, 0, 4);
            $candidates[] = substr($clean, 0, 5);
        } elseif ($wordCount === 2) {
            $w1 = preg_replace('/[^A-Z]/', '', strtoupper($words[0]));
            $w2 = preg_replace('/[^A-Z]/', '', strtoupper($words[1]));
            $w1 = str_pad($w1, 3, 'X');
            $w2 = str_pad($w2, 3, 'X');
            $candidates[] = substr($w1, 0, 2) . substr($w2, 0, 2);
            $candidates[] = substr($w1, 0, 3) . substr($w2, 0, 2);
            $candidates[] = substr($w1, 0, 2) . substr($w2, 0, 3);
        } else {
            $initials = array_map(fn($w) => strtoupper($w[0]), $words);
            $initialsStr = preg_replace('/[^A-Z]/', '', implode('', $initials));
            $initialsStr = str_pad($initialsStr, 5, 'X');
            $candidates[] = substr($initialsStr, 0, 4);
            $candidates[] = substr($initialsStr, 0, 5);
            $w1 = preg_replace('/[^A-Z]/', '', strtoupper($words[0]));
            $wLast = preg_replace('/[^A-Z]/', '', strtoupper(end($words)));
            $w1 = str_pad($w1, 2, 'X');
            $wLast = str_pad($wLast, 2, 'X');
            $candidates[] = substr($w1, 0, 2) . substr($initialsStr, 1, 1) . substr($wLast, 0, 2);
        }

        $candidates = array_unique(array_filter($candidates, fn($c) => strlen($c) >= 4 && strlen($c) <= 5));

        foreach ($candidates as $candidate) {
            if (!static::withTrashed()->where('short_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        $base = $candidates[0] ?? str_pad(strtoupper(preg_replace('/[^A-Z]/', '', strtoupper($companyName))), 4, 'X');
        $base4 = substr($base, 0, 4);

        foreach (range('A', 'Z') as $suffix) {
            $attempt = $base4 . $suffix;
            if (!static::withTrashed()->where('short_id', $attempt)->exists()) {
                return $attempt;
            }
        }

        $base3 = substr($base, 0, 3);
        foreach (range('A', 'Z') as $s1) {
            foreach (range('A', 'Z') as $s2) {
                $attempt = $base3 . $s1 . $s2;
                if (!static::withTrashed()->where('short_id', $attempt)->exists()) {
                    return $attempt;
                }
            }
        }

        return $base4 . strtoupper(substr(md5($companyName . microtime()), 0, 1));
    }
}

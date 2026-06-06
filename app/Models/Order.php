<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'order_number',
        'shopify_order_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'billing_name',
        'billing_street',
        'billing_address1',
        'billing_address2',
        'billing_company',
        'billing_city',
        'billing_zip',
        'billing_province',
        'billing_country',
        'billing_phone',
        'shipping_name',
        'shipping_street',
        'shipping_address1',
        'shipping_address2',
        'shipping_company',
        'shipping_city',
        'shipping_zip',
        'shipping_province',
        'shipping_country',
        'shipping_phone',
        'financial_status',
        'fulfillment_status',
        'payment_method',
        'payment_reference',
        'currency',
        'subtotal',
        'shipping_cost',
        'taxes',
        'total',
        'discount_code',
        'discount_amount',
        'shipping_method',
        'outstanding_balance',
        'refunded_amount',
        'paid_at',
        'fulfilled_at',
        'cancelled_at',
        'notes',
        'note_attributes',
        'tags',
        'risk_level',
        'source',
        'vendor',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_id',
        'ip_address',
        'accepts_marketing',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'taxes' => 'decimal:2',
        'total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'accepts_marketing' => 'boolean',
        'note_attributes' => 'json',
        'tags' => 'array',
    ];

    /**
     * Get the order items for the order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the client that owns the order.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function latestShipment()
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope a query to only include orders with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('fulfillment_status', $status);
    }

    /**
     * Scope a query to only include orders with a specific financial status.
     */
    public function scopeWithFinancialStatus($query, $status)
    {
        return $query->where('financial_status', $status);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to search orders.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%");
        });
    }

    /**
     * Get the formatted total attribute.
     */
    public function getFormattedTotalAttribute()
    {
        return $this->currency . ' ' . number_format($this->total, 2);
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColorAttribute()
    {
        return match ($this->fulfillment_status) {
            'fulfilled' => 'success',
            'pending' => 'warning',
            'unfulfilled' => 'info',
            'cancelled' => 'error',
            default => 'default',
        };
    }

    /**
     * Get the financial status color for UI display.
     */
    public function getFinancialStatusColorAttribute()
    {
        return match ($this->financial_status) {
            'paid' => 'success',
            'pending' => 'warning',
            'refunded' => 'error',
            'partially_refunded' => 'warning',
            default => 'default',
        };
    }
}

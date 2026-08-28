<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Hide Shopify orders that are awaiting client review (manual-approval mode)
     * or that the client dismissed. Only normal orders (null) and approved ones
     * are visible to every existing admin/portal query. The Shopify Queue tab
     * opts out via ->withoutGlobalScope('shopify_visible').
     */
    protected static function booted(): void
    {
        static::addGlobalScope('shopify_visible', function ($builder) {
            $builder->where(function ($query) {
                $query->whereNull('shopify_sync_status')
                    ->orWhere('shopify_sync_status', 'approved');
            });
        });
    }

    /**
     * Call disposition — the outcome of the ops confirmation call. A separate
     * axis from `fulfillment_status`, which stays Shopify-shaped.
     */
    public const CALL_NOT_CALLED = 'not_called';
    public const CALL_NO_ANSWER = 'no_answer';
    public const CALL_CONFIRMED = 'confirmed';
    public const CALL_CANCELLED = 'cancelled';
    public const CALL_WRONG_NUMBER = 'wrong_number';

    public const CALL_STATUSES = [
        self::CALL_NOT_CALLED,
        self::CALL_NO_ANSWER,
        self::CALL_CONFIRMED,
        self::CALL_CANCELLED,
        self::CALL_WRONG_NUMBER,
    ];

    /**
     * WhatsApp conversation flow state. Per-message delivery state (delivered/
     * read/failed) lives on `whatsapp_messages` instead — see
     * {@see \App\Models\WhatsAppMessage}.
     */
    public const WHATSAPP_SENT = 'sent';
    public const WHATSAPP_FOLLOWUP_SENT = 'followup_sent';
    public const WHATSAPP_REPLIED = 'replied';
    public const WHATSAPP_CONFIRMED = 'confirmed';
    public const WHATSAPP_GRAVEYARD = 'graveyard';
    public const WHATSAPP_FAILED = 'failed';

    protected $fillable = [
        'client_id',
        'order_number',
        'shopify_order_id',
        'shopify_shop_domain',
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
        'call_status',
        'call_attempts',
        'last_called_at',
        'call_notes',
        'whatsapp_status',
        'whatsapp_phone_e164',
        'whatsapp_sent_at',
        'whatsapp_followup_sent_at',
        'whatsapp_replied_at',
        'whatsapp_delivered_at',
        'whatsapp_read_at',
        'whatsapp_reply_message',
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
        'cod_collected_amount',
        'cod_collected_at',
        'paid_at',
        'fulfilled_at',
        'cancelled_at',
        'notes',
        'note_attributes',
        'tags',
        'shopify_raw_tags',
        'risk_level',
        'source',
        'vendor',
        'shopify_sync_status',
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
        'cod_collected_amount' => 'decimal:2',
        'cod_collected_at' => 'datetime',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_called_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'whatsapp_followup_sent_at' => 'datetime',
        'whatsapp_replied_at' => 'datetime',
        'whatsapp_delivered_at' => 'datetime',
        'whatsapp_read_at' => 'datetime',
        'accepts_marketing' => 'boolean',
        'note_attributes' => 'json',
        'tags' => 'array',
        'shopify_raw_tags' => 'array',
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
     * When WhatsApp's 24-hour customer service window closes for this order.
     *
     * The customer opens it by messaging us; it runs 24h from their most recent
     * inbound message. Inside it an agent may send free-form text. Outside it,
     * Meta only accepts approved templates — so this is what gates the reply
     * box in the inbox. Null means no window was ever opened.
     */
    public function whatsAppWindowExpiresAt(): ?\Illuminate\Support\Carbon
    {
        $lastInbound = $this->whatsappMessages()
            ->where('direction', WhatsAppMessage::DIRECTION_INBOUND)
            ->latest('created_at')
            ->value('created_at');

        return $lastInbound ? \Illuminate\Support\Carbon::parse($lastInbound)->addHours(24) : null;
    }

    public function whatsAppWindowIsOpen(): bool
    {
        $expiry = $this->whatsAppWindowExpiresAt();

        return $expiry !== null && $expiry->isFuture();
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /**
     * Orders the WhatsApp confirmation flow is still actively chasing.
     */
    public function scopeAwaitingWhatsAppReply($query)
    {
        return $query->whereIn('whatsapp_status', [self::WHATSAPP_SENT, self::WHATSAPP_FOLLOWUP_SENT]);
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
     * Scope a query to only include orders with shipments assigned to a courier.
     */
    public function scopeWithShipment($query)
    {
        return $query->whereHas('shipments');
    }

    /**
     * Scope a query to only include orders without shipments (not assigned to courier).
     */
    public function scopeWithoutShipment($query)
    {
        return $query->whereDoesntHave('shipments');
    }

    /**
     * Get the formatted total attribute.
     */
    public function getFormattedTotalAttribute()
    {
        return $this->currency . ' ' . number_format($this->total, 2);
    }

    /**
     * Whether this order should be collected as Cash on Delivery.
     *
     * `payment_method` is not normalized across sources: Shopify orders store
     * 'cod'/'prepaid', CSV imports store free text like 'Cash on Delivery' or
     * 'COD' (or Arabic), and manual orders may leave it null. So we detect COD
     * positively from known keywords, treat clearly-prepaid values as not-COD,
     * and for anything ambiguous fall back to the financial status (an order
     * that is already 'paid' is not collected on delivery).
     */
    public function isCashOnDelivery(): bool
    {
        $method = mb_strtolower(trim((string) $this->payment_method));

        $codKeywords = ['cod', 'cash on delivery', 'cash', 'الدفع عند الاستلام', 'الدفع عند الإستلام', 'دفع عند الاستلام'];
        foreach ($codKeywords as $keyword) {
            if ($method !== '' && str_contains($method, $keyword)) {
                return true;
            }
        }

        $prepaidKeywords = ['prepaid', 'paid', 'card', 'credit', 'mada', 'apple pay', 'visa', 'mastercard', 'tabby', 'tamara', 'stc', 'online', 'bank'];
        foreach ($prepaidKeywords as $keyword) {
            if ($method !== '' && str_contains($method, $keyword)) {
                return false;
            }
        }

        // Unknown/blank payment method: an already-paid order is not COD.
        return mb_strtolower(trim((string) $this->financial_status)) !== 'paid';
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ClientProduct;
use App\Models\Product;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'lineitem_name',
        'lineitem_quantity',
        'lineitem_price',
        'lineitem_compare_at_price',
        'lineitem_sku',
        'lineitem_requires_shipping',
        'lineitem_taxable',
        'lineitem_fulfillment_status',
        'lineitem_discount',
        'variant_name',
        'client_product_id',
        'product_id',
    ];

    protected $casts = [
        'lineitem_quantity' => 'integer',
        'lineitem_price' => 'decimal:2',
        'lineitem_compare_at_price' => 'decimal:2',
        'lineitem_discount' => 'decimal:2',
        'lineitem_requires_shipping' => 'boolean',
        'lineitem_taxable' => 'boolean',
    ];

    protected $appends = [
        'total_price',
        'formatted_price',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function clientProduct()
    {
        return $this->belongsTo(ClientProduct::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the total price for this line item.
     */
    public function getTotalPriceAttribute()
    {
        return $this->lineitem_price * $this->lineitem_quantity;
    }

    /**
     * Get the formatted price.
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->lineitem_price, 2);
    }
}

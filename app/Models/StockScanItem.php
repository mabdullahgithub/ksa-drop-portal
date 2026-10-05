<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item in a scanned parcel, and what the scan did to its product's stock:
 * units out (below zero) or back in.
 */
class StockScanItem extends Model
{
    protected $fillable = [
        'stock_scan_id',
        'product_id',
        'client_product_id',
        'name',
        'sku',
        'quantity',
        'stock_after',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_after' => 'integer',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(StockScan::class, 'stock_scan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function clientProduct(): BelongsTo
    {
        return $this->belongsTo(ClientProduct::class)->withTrashed();
    }

    /**
     * The item was linked to a product, so the scan changed its stock.
     */
    public function isMatched(): bool
    {
        return $this->product_id !== null || $this->client_product_id !== null;
    }
}

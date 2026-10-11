<?php

namespace App\Models;

use App\Models\Concerns\RestrictedByClientAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dropshipper's sell count for one catalogue product — see
 * App\Services\Inventory\StockScanRecorder, the only thing that changes it.
 */
class DropshipSale extends Model
{
    use RestrictedByClientAccess;

    protected $fillable = [
        'client_id',
        'product_id',
        'orders',
        'units',
    ];

    protected $casts = [
        'orders' => 'integer',
        'units' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

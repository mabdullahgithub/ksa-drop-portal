<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClientProductImage extends Model
{
    protected $fillable = ['client_product_id', 'path', 'position', 'alt_text'];

    protected $appends = ['url'];

    public function clientProduct(): BelongsTo
    {
        return $this->belongsTo(ClientProduct::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}

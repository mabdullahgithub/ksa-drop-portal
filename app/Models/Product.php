<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'handle', 'title', 'body_html', 'vendor', 'product_category', 'type', 'tags', 'published',
        'variant_sku', 'variant_price', 'variant_compare_at_price', 'cost_per_item',
        'variant_inventory_qty', 'variant_inventory_policy', 'variant_fulfillment_service',
        'variant_requires_shipping', 'variant_taxable', 'variant_grams', 'variant_barcode',
        'option1_name', 'option1_value', 'option2_name', 'option2_value', 'option3_name', 'option3_value',
        'seo_title', 'seo_description',
        'available_in', 'uae_prices',
        'included_saudi_arabia', 'price_saudi_arabia', 'compare_at_price_saudi_arabia',
        'included_pakistan', 'price_pakistan', 'compare_at_price_pakistan',
        'status', 'primary_image',
    ];

    protected $casts = [
        'tags' => 'array',
        'available_in' => 'array',
        'uae_prices' => 'array',
        'published' => 'boolean',
        'variant_requires_shipping' => 'boolean',
        'variant_taxable' => 'boolean',
        'included_saudi_arabia' => 'boolean',
        'included_pakistan' => 'boolean',
        'variant_price' => 'decimal:2',
        'variant_compare_at_price' => 'decimal:2',
        'cost_per_item' => 'decimal:2',
        'price_saudi_arabia' => 'decimal:2',
        'compare_at_price_saudi_arabia' => 'decimal:2',
        'price_pakistan' => 'decimal:2',
        'compare_at_price_pakistan' => 'decimal:2',
        'variant_grams' => 'decimal:2',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('handle', 'like', "%{$term}%")
              ->orWhere('variant_sku', 'like', "%{$term}%")
              ->orWhere('vendor', 'like', "%{$term}%")
              ->orWhere('type', 'like', "%{$term}%");
        });
    }
}

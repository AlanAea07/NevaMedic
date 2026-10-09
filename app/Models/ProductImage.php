<?php

namespace App\Models;

use App\Services\ImageProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_variant_id', 'path', 'external_url', 'alt', 'sort_order', 'is_primary'];

    public function getUrlAttribute(): string
    {
        $path = ImageProcessor::isProcessed($this->path) ? $this->path.'.webp' : $this->path;

        return $this->external_url ?: asset('storage/'.ltrim($path, '/'));
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

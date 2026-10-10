<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'product_type', 'short_description', 'description', 'material', 'care_instructions', 'reference_price', 'currency', 'is_available', 'is_featured', 'is_published', 'has_variants', 'available_sizes'];

    protected function casts(): array
    {
        return ['reference_price' => 'decimal:2', 'is_available' => 'boolean', 'is_featured' => 'boolean', 'is_published' => 'boolean', 'has_variants' => 'boolean', 'available_sizes' => 'array'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function getCoverImageAttribute(): ?ProductImage
    {
        return $this->images->firstWhere('product_variant_id', null) ?? ($this->has_variants ? $this->images->first() : null);
    }

    public function getCatalogImagesAttribute()
    {
        $colors = $this->has_variants ? $this->variants->where('is_available', true)->modelKeys() : [];

        return $this->images->filter(fn ($image) => $image->product_variant_id === null || in_array($image->product_variant_id, $colors, true));
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(ProductDailyMetric::class);
    }
}

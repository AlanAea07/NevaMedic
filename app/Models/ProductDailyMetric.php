<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDailyMetric extends Model
{
    public $timestamps = false;

    protected $fillable = ['date', 'views', 'whatsapp_clicks'];

    protected function casts(): array
    {
        return ['date' => 'date', 'views' => 'integer', 'whatsapp_clicks' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

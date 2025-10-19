<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEmbedding extends Model
{
    protected $fillable = [
        'product_id',
        'image_id',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    /**
     * Get the product that owns the embedding.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product image that owns the embedding.
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCategories extends Model
{
    protected $table = 'store_categories';

    protected $fillable = [
        'category_id',
        'store_id'
    ];
    /**
     * Get the category that owns the StoreCategories
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}

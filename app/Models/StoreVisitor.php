<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreVisitor extends Model
{
    protected $fillable = [
        'store_id',
        'user_id',
        'product_id',
        'visit_type',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the store that was visited
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the visitor (user)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that was visited (if any)
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

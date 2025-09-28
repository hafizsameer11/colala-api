<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreAddress extends Model
{
    //
    protected $fillable = [
        'store_id',
        'state',
        'local_government',
        'full_address',
        'is_main',
        'opening_hours',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'opening_hours' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}

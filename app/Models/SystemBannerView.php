<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemBannerView extends Model
{
    protected $fillable = [
        'banner_id',
        'user_id',
        'ip_address',
        'user_agent',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    // Relationships
    public function banner()
    {
        return $this->belongsTo(SystemBanner::class, 'banner_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

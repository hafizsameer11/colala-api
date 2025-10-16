<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemBannerClick extends Model
{
    protected $fillable = [
        'banner_id',
        'user_id',
        'ip_address',
        'user_agent',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
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

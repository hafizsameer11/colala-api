<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemBanner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'image',
        'link',
        'audience_type', // 'all', 'buyers', 'sellers', 'specific'
        'target_user_ids', // JSON array of user IDs for specific audience
        'position', // 'top', 'middle', 'bottom'
        'is_active',
        'start_date',
        'end_date',
        'created_by', // Admin user ID who created the banner
    ];

    protected $casts = [
        'target_user_ids' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bannerViews()
    {
        return $this->hasMany(SystemBannerView::class, 'banner_id');
    }

    public function bannerClicks()
    {
        return $this->hasMany(SystemBannerClick::class, 'banner_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                   ->where(function ($q) {
                       $q->whereNull('start_date')
                         ->orWhere('start_date', '<=', now());
                   })
                   ->where(function ($q) {
                       $q->whereNull('end_date')
                         ->orWhere('end_date', '>=', now());
                   });
    }

    public function scopeForAudience($query, $audienceType)
    {
        return $query->where(function ($q) use ($audienceType) {
            $q->where('audience_type', 'all')
              ->orWhere('audience_type', $audienceType);
        });
    }

    // Helper methods
    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function getTotalViewsAttribute()
    {
        return $this->bannerViews()->count();
    }

    public function getTotalClicksAttribute()
    {
        return $this->bannerClicks()->count();
    }

    public function getClickThroughRateAttribute()
    {
        $views = $this->getTotalViewsAttribute();
        $clicks = $this->getTotalClicksAttribute();
        
        return $views > 0 ? round(($clicks / $views) * 100, 2) : 0;
    }

    public function isCurrentlyActive()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        
        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }
}

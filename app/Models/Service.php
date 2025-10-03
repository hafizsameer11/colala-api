<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
     protected $fillable = [
        'store_id','category_id','name','short_description',
        'full_description','price_from','price_to','discount_price','status',
            'service_category_id', // ✅
    ];


    public function media()
    {
        return $this->hasMany(ServiceMedia::class);
    }

    public function subServices()
    {
        return $this->hasMany(SubService::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    public function serviceCategory()
{
    return $this->belongsTo(ServiceCategory::class, 'service_category_id');
}
public function stats(){
    return $this->hasMany(ServiceStat::class);
}
 // ✅ Summary counts by event_type
    public function statsSummary(): array
    {
        $defaults = [
            'view' => 0,
            'impression' => 0,
            'click' => 0,
            'chat' => 0,
            'phone_view' => 0,
        ];

        $counts = $this->stats()
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->toArray();

        return array_merge($defaults, $counts);
    }

}

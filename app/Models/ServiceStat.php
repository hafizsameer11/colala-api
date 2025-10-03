<?php 

// app/Models/ServiceStat.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceStat extends Model
{
    protected $fillable = [
        'service_id','event_type','user_id','ip'
    ];

    public function service() {
        return $this->belongsTo(Service::class);
    }
}

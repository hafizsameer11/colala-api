<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['store_id','message','impressions'];

    public function store() {
        return $this->belongsTo(Store::class);
    }
}
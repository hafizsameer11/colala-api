<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreBusinessDetail extends Model
{
    //
       protected $fillable = [
        'store_id','registered_name','business_type','nin_number','bn_number','cac_number',
        'nin_document','cac_document','utility_bill','store_video','has_physical_store'
    ];
    public function store() { return $this->belongsTo(Store::class); }
}

<?php 

// app/Models/SubscriptionPlan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name','price','currency','duration_days','features','apple_product_id_monthly','apple_product_id_annual'];
    protected $casts = ['features' => 'array'];

    public function subscriptions() {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
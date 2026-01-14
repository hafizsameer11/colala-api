<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'price'    => $this->price,
            'currency' => $this->currency,
            'duration_days' => $this->duration_days,
            'features' => $this->features,
            'apple_product_id_monthly' => $this->apple_product_id_monthly,
            'apple_product_id_annual' => $this->apple_product_id_annual,
        ];
    }
}

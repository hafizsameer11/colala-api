<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
     public function toArray($request) {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'discount_type' => $this->discount_type == 1 ? 'percentage' : 'fixed',
            'discount_value'=> $this->discount_value,
            'max_usage'     => $this->max_usage,
            'usage_per_user'=> $this->usage_per_user,
            'times_used'    => $this->times_used,
            'expiry_date'   => $this->expiry_date?->toDateString(),
            'status'        => $this->status,
            'created_at'    => $this->created_at->toDateTimeString(),
        ];
    }
}

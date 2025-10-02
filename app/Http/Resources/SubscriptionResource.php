<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
  public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'plan'        => new SubscriptionPlanResource($this->whenLoaded('plan')),
            'status'      => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'start_date'  => $this->start_date->toDateString(),
            'end_date'    => $this->end_date->toDateString(),
        ];
    }
}

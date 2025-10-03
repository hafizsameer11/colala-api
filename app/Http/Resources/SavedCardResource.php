<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request) {
        return [
            'id'          => $this->id,
            'card_holder' => $this->card_holder,
            'last4'       => "**** **** **** {$this->last4}",
            'brand'       => $this->brand,
            'expiry'      => "{$this->expiry_month}/{$this->expiry_year}",
            'is_active'   => $this->is_active,
            'is_autodebit'=> $this->is_autodebit,
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}

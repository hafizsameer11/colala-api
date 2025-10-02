<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
     public function toArray($request) {
        return [
            'id'         => $this->id,
            'message'    => $this->message,
            'impressions'=> $this->impressions,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

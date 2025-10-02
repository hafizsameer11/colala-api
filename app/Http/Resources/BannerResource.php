<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
   public function toArray($request) {
        return [
            'id'         => $this->id,
            'image_url'  => asset('storage/'.$this->image_path),
            'link'       => $this->link,
            'impressions'=> $this->impressions,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

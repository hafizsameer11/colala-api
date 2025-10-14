<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoostProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'product_id'     => $this->product_id,
            'store_id'       => $this->store_id,
            'location'       => $this->location,
            'duration'       => $this->duration,
            'budget'         => $this->budget,
            'total_amount'   => $this->total_amount,
            'reach'          => $this->reach,
            'impressions'    => $this->impressions,
            'clicks'         => $this->clicks,
            'cpc'            => $this->cpc,
            'status'         => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'start_date'     => optional($this->start_date)->toDateString(),
            'created_at'     => $this->created_at->toIso8601String(),
            'product' => [
                'id'    => $this->product->id ?? null,
                'name'  => $this->product->name ?? null,
                'price' => $this->product->price ?? null,
                'discount_price' => $this->product->discount_price ?? null,
                'final_price' => isset($this->product->discount_price) && $this->product->discount_price > 0
                    ? $this->product->discount_price
                    : ($this->product->price ?? null),
                'images'=> $this->product && $this->product->images 
                    ? $this->product->images->map(fn($img) => [
                        'id'  => $img->id,
                        'url' => asset('storage/'.$img->path)
                    ])
                    : [],
            ],
            
        ];
    }
}

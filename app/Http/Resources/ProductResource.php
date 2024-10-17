<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Debugging: Check if pivot exists
        // You can remove this after debugging
        if (!isset($this->pivot)) {
            \Log::debug('Pivot not set in ProductResource', ['resource' => $this]);
        }

        return [
            'identify'    => $this->uuid,
            'flag'        => $this->flag,
            'title'       => $this->title,
            'image'       => url("storage/{$this->image}"),
            'price'       => $this->price,
            'description' => $this->description,
            // 'quantity'    => optional($this->pivot)->qty ?? 0,
        ];
    }
}

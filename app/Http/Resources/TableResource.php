<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'identify' => $this->uuid,
            'name' => $this->identify,
            'description' => $this->description,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'beach_row' => $this->beach_row,
            'beach_col' => $this->beach_col,
        ];
    }
}

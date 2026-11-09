<?php

namespace Solt\Bonus\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LegalEntityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'inn'           => $this->inn,
            'name'          => $this->name
        ];
    }
}

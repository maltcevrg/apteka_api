<?php

namespace Solt\Bonus\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Solt\Bonus\Http\Resources\CardResource;

class BonusBalanceResource extends JsonResource
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
            'card'          => new CardResource($this->whenLoaded('card')),
            'legal_entity'  => new LegalEntityResource($this->whenLoaded('legal_entity')),
            'sum'           => $this->sum
        ];
    }
}

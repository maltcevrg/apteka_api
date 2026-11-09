<?php

namespace Solt\Bonus\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardOperationResource extends JsonResource
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
            'id'                => $this->id,
            'card'              => new CardResource( $this->whenLoaded('card') ),
            'legal_entity'      => new LegalEntityResource( $this->whenLoaded('legal_entity') ),
            'pharmacy'          => new PharmacyResource( $this->whenLoaded('pharmacy') ),
            'action'            => $this->action,
            'document_date'     => $this->document_date,
            'document_number'   => $this->document_number,
            'document_type'     => $this->document_type,
            'amount'            => $this->amount
        ];
    }
}

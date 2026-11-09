<?php

namespace Solt\Bonus\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Solt\Bonus\Models\Card;

class CardResource extends JsonResource
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
            'short_code'        => $this->short_code,
            'firstname'         => $this->firstname,
            'name'              => $this->name,
            'lastname'          => $this->lastname,
            'birthdate'         => Carbon::parse($this->birthdate)->format('d.m.Y'),
            'email'             => $this->email,
            'phone'             => $this->phone,
            'city'              => $this->city,
            'gender'            => Card::$genders[$this->gender],
            'newsletter_agree'  => (Bool)$this->newsletter_agree,
            'guid'              => $this->guid,
            'status'            => Card::$statuses[$this->status]
        ];
    }
}

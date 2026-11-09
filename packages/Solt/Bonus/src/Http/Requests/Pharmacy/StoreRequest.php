<?php

namespace Solt\Bonus\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest{

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'legal_entity'      => ['required', 'numeric', 'exists:legal_entities,id', 'unique:pharmacies,legal_entity_id,NULL,id,deleted_at,NULL,number,'.$this->number],
            'number'            => ['required', 'numeric', 'between:1,99', 'unique:pharmacies,number,NULL,id,deleted_at,NULL'],
            'name'              => ['required', 'string', 'max:255']
        ];
    }
}

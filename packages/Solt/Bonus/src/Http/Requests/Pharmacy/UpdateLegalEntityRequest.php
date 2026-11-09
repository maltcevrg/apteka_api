<?php

namespace Solt\Bonus\Http\Requests\Pharmacy;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalEntityRequest extends FormRequest{

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'legal_entity'      => ['required', 'numeric', 'exists:legal_entities,id', 'unique:pharmacies,legal_entity_id,'.$this->legal_entity.',legal_entity_id,deleted_at,NULL,number,'.$this->number]
        ];
    }
}

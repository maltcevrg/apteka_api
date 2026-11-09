<?php

namespace Solt\Bonus\Http\Requests\LegalEntity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest{

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'inn'               => ['sometimes', 'regex:/^[0-9]{10,20}$/i', 'unique:legal_entities,inn,'.$this->inn.',inn,deleted_at,NULL'],
            'name'              => ['sometimes', 'string', 'max:255']
        ];
    }
}

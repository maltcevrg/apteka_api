<?php

namespace Solt\Bonus\Http\Requests\LegalEntity;

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
            'inn'               => ['required', 'regex:/^[0-9]{10,20}$/i', 'unique:legal_entities,inn,NULL,id,deleted_at,NULL'],
            'name'              => ['required', 'string', 'max:255']
        ];
    }
}

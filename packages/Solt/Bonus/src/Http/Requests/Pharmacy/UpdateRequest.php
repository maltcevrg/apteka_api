<?php

namespace Solt\Bonus\Http\Requests\Pharmacy;

use Illuminate\Validation\Rule;
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
            'number'            => ['sometimes', 'numeric', 'between:1,99', 'unique:pharmacies,number,'.$this->number.',number,deleted_at,NULL'],
            'name'              => ['sometimes', 'string', 'max:255']
        ];
    }
}

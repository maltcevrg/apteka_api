<?php

namespace Solt\Bonus\Http\Requests\Card;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class FindRequest extends FormRequest{

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'phone' => ['required', 'string', 'exists:cards,phone', 'max:12', 'regex:/(\+7)[0-9]{10}/']
        ];
    }
}

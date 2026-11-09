<?php

namespace Solt\Bonus\Http\Requests\Card;

use Carbon\Carbon;
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
            'short_code' => ['required', 'string', 'unique:cards,short_code,'.$this->short_code.',short_code,deleted_at,NULL', 'min:9', 'max:13'],
            'firstname' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'lastname' => ['sometimes', 'string', 'max:255'],
            'birthdate' => ['sometimes', 'date'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'unique:cards,phone,'.$this->short_code.',short_code,deleted_at,NULL', 'max:12', 'regex:/(\+7)[0-9]{10}/'],
            'city'  => ['sometimes', 'string', 'max:255'],
            'gender'  => ['sometimes', 'integer', 'between:0,1'],
            'newsletter_agree' => ['sometimes', 'integer', 'between:0,1'],
            'guid'  => ['sometimes', 'string', 'max:255'],
            'status'  => ['sometimes', 'integer', 'between:0,2']
        ];
    }

    protected function passedValidation()
    {
        if( !empty( $this->birthdate ) ){
            $this->merge(['birthdate' => Carbon::parse($this->birthdate)->format('Y-m-d H:i:s')]);
        }
    }
}

<?php

namespace Solt\Bonus\Http\Requests\CardOperation;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Solt\Bonus\Models\CardOperation;

class IncreaseReturnRequest extends FormRequest{

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'short_code'        => ['required', 'string', 'exists:cards,short_code', 'min:9', 'max:13'],
            'pharmacy_number'   => ['required', 'numeric', 'between:1,99', 'exists:pharmacies,number'],
            'inn'               => ['required', 'regex:/^[0-9]{10,20}$/i', 'exists:legal_entities,inn'],
            'document_date'     => ['required', 'date'],
            'document_number'   => ['required', 'string', 'max:12', 'unique:card_operations,document_number,NULL,id,deleted_at,NULL'],
            'document_type'     => ['required', 'string', 'max:40'],
            'amount'            => ['required', 'numeric', 'max:-0.01']
        ];
    }

    protected function passedValidation()
    {
        $this->merge(['document_date' => Carbon::parse($this->document_date)->format('Y-m-d H:i:s')]);
        $this->merge(['action' => CardOperation::$increase_return_action]);
    }
}

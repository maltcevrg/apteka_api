<?php

namespace Solt\Bonus\Http\Requests\CardOperation;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Solt\Bonus\Models\CardOperation;

class MoveRequest extends FormRequest{

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'short_code_from'   => ['required', 'string', 'exists:cards,short_code', 'min:9', 'max:13'],
            'short_code_to'     => ['required', 'string', 'exists:cards,short_code', 'min:9', 'max:13'],
            'document_date'     => ['required', 'date'],
            'document_number'   => ['required', 'string', 'max:12', 'unique:card_operations,document_number,NULL,id,deleted_at,NULL'],
            'document_type'     => ['required', 'string', 'max:40'],
        ];
    }

    protected function passedValidation()
    {
        $this->merge(['document_date' => Carbon::parse($this->document_date)->format('Y-m-d H:i:s')]);
        $this->merge(['action' => CardOperation::$move_action]);
    }
}

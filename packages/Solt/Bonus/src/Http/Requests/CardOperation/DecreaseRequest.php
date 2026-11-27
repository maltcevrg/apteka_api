<?php

namespace Solt\Bonus\Http\Requests\CardOperation;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Solt\Bonus\Models\CardOperation;

class DecreaseRequest extends FormRequest{

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
            'document_number'   => ['required', 'string', 'max:12'],
            'document_time'     => ['sometimes', 'nullable', 'date_format:H:i,H:i:s'],
            'document_type'     => ['required', 'string', 'max:40'],
            'offline'           => ['sometimes', 'boolean'],
            'amount'            => ['required', 'numeric', 'max:-0.01']
        ];
    }

    protected function passedValidation()
    {
        $date = Carbon::parse($this->document_date);
        $time = $this->input('document_time');
        if (!$time && ($date->hour || $date->minute || $date->second)) {
            $time = $date->format('H:i:s'); // время передано внутри даты
        }
        if (!$time && !$this->boolean('offline')) {
            $time = now()->format('H:i:s'); // онлайн-чек — сейчас
        }
        $this->merge(['document_date' => $date->format('Y-m-d'), 'document_time' => $time ?: null]);
        $this->merge(['action' => CardOperation::$decreser_action]);
    }
}

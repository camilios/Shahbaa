<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GovernorateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('governorates', 'name')->ignore($this->route('governorate')),
            ],
        ];
    }
}

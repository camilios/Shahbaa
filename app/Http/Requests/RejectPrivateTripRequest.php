<?php

namespace App\Http\Requests;

class RejectPrivateTripRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }
}

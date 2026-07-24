<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RealtimeTrackingAuthorizationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'action' => ['required', 'string', Rule::in(['publish', 'subscribe'])],
        ];
    }
}

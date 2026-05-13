<?php

namespace App\Http\Requests;

class PrivateTripRequestRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'user_id' => 'required|exists:users,id',
                'from_location' => 'required|string|max:255',
                'to_location' => 'required|string|max:255',
                'status' => 'nullable|string|max:100',
            ];
        }

        return [
            'user_id' => 'sometimes|required|exists:users,id',
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
        ];
    }
}

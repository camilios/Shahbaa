<?php

namespace App\Http\Requests;

class CheckpointRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'name' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'governorate' => 'nullable|string|max:255',
            ];
        }

        return [
            'name' => 'sometimes|required|string|max:255',
            'location' => 'nullable|string|max:255',
            'governorate' => 'nullable|string|max:255',
        ];
    }
}

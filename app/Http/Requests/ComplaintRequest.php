<?php

namespace App\Http\Requests;

class ComplaintRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'customer_id' => 'required|exists:users,id',
                'comment' => 'required|string',
                'status' => 'nullable|string|max:100',
            ];
        }

        return [
            'customer_id' => 'sometimes|required|exists:users,id',
            'comment' => 'nullable|string',
            'status' => 'nullable|string|max:100',
        ];
    }
}

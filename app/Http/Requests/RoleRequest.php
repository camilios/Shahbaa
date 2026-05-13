<?php

namespace App\Http\Requests;

class RoleRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'name' => 'required|string|max:255',
                'user_id' => 'required|exists:users,id',
            ];
        }

        return [
            'name' => 'sometimes|required|string|max:255',
            'user_id' => 'sometimes|required|exists:users,id',
        ];
    }
}

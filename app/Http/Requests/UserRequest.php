<?php

namespace App\Http\Requests;

class UserRequest extends ApiRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        if ($this->isMethod('POST')) {
            return [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'phone' => 'nullable|string|max:50',
                'national_number' => 'nullable|string|max:255|unique:users,national_number',
                'father_name' => 'nullable|string|max:255',
                'gender' => 'nullable|string|max:20',
                'role' => 'nullable|string|max:50',
                'status' => 'nullable|string|max:50',
            ];
        }

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $userId,
            'password' => 'sometimes|nullable|string|min:8',
            'phone' => 'nullable|string|max:50',
            'national_number' => 'nullable|string|max:255|unique:users,national_number,' . $userId,
            'father_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:20',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ];
    }
}

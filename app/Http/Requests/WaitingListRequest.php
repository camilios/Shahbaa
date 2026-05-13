<?php

namespace App\Http\Requests;

class WaitingListRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'user_id' => 'required|exists:users,id',
                'trip_id' => 'required|exists:trips,id',
                'status' => 'nullable|string|max:100',
            ];
        }

        return [
            'user_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'status' => 'nullable|string|max:100',
        ];
    }
}

<?php

namespace App\Http\Requests;

class DriverRequestRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'driver_id' => 'required|exists:users,id',
                'trip_id' => 'required|exists:trips,id',
                'notes' => 'nullable|string',
                'status' => 'nullable|string|max:100',
            ];
        }

        return [
            'driver_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|max:100',
        ];
    }
}

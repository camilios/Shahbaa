<?php

namespace App\Http\Requests;

class TripRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'driver_id' => 'required|exists:users,id',
                'type' => 'required|string|max:255',
                'point_price' => 'nullable|numeric|min:0',
                'money_price' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|max:100',
                'departure_date' => 'nullable|date',
                'arrival_date' => 'nullable|date',
                'total_seats' => 'nullable|integer|min:0',
                'available_seats' => 'nullable|integer|min:0',
                'earned_points' => 'nullable|integer|min:0',
            ];
        }

        return [
            'driver_id' => 'sometimes|required|exists:users,id',
            'type' => 'sometimes|required|string|max:255',
            'point_price' => 'nullable|numeric|min:0',
            'money_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:100',
            'departure_date' => 'nullable|date',
            'arrival_date' => 'nullable|date',
            'total_seats' => 'nullable|integer|min:0',
            'available_seats' => 'nullable|integer|min:0',
            'earned_points' => 'nullable|integer|min:0',
        ];
    }
}

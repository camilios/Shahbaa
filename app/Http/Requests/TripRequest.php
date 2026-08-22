<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TripRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'driver_id' => ['required', Rule::exists('users', 'id')->where('role', 'driver')->where('status', 'active')],
                'type' => 'required|string|max:255',
                'source_governorate_id' => ['required', 'integer', 'exists:governorates,id', 'different:destination_governorate_id'],
                'destination_governorate_id' => ['required', 'integer', 'exists:governorates,id', 'different:source_governorate_id'],
                'point_price' => 'nullable|numeric|min:0',
                'money_price' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|max:100',
                'departure_date' => 'nullable|date',
                'arrival_date' => 'nullable|date',
                'total_seats' => 'required|integer|min:1|max:50',
                'checkpoint_ids' => 'required|array|min:2',
                'checkpoint_ids.*' => 'required|integer|distinct|exists:checkpoints,id',
                'earned_points' => 'nullable|integer|min:0',
            ];
        }

        return [
            'driver_id' => ['sometimes', 'required', Rule::exists('users', 'id')->where('role', 'driver')->where('status', 'active')],
            'type' => 'sometimes|required|string|max:255',
            'source_governorate_id' => ['sometimes', 'required', 'integer', 'exists:governorates,id', 'different:destination_governorate_id'],
            'destination_governorate_id' => ['sometimes', 'required', 'integer', 'exists:governorates,id', 'different:source_governorate_id'],
            'point_price' => 'nullable|numeric|min:0',
            'money_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:100',
            'departure_date' => 'nullable|date',
            'arrival_date' => 'nullable|date',
            'total_seats' => 'sometimes|integer|min:1|max:50',
            'checkpoint_ids' => 'sometimes|array|min:2',
            'checkpoint_ids.*' => 'required|integer|distinct|exists:checkpoints,id',
            'earned_points' => 'nullable|integer|min:0',
        ];
    }
}

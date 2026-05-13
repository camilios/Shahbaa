<?php

namespace App\Http\Requests;

class BookingRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'user_id' => 'required|exists:users,id',
                'driver_id' => 'required|exists:users,id',
                'trip_id' => 'required|exists:trips,id',
                'pickup_checkpoint_id' => 'required|exists:checkpoints,id',
                'dropoff_checkpoint_id' => 'required|exists:checkpoints,id',
                'seats_count' => 'required|integer|min:1',
                'status' => 'nullable|string|max:100',
            ];
        }

        return [
            'user_id' => 'sometimes|required|exists:users,id',
            'driver_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'pickup_checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'dropoff_checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'seats_count' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:100',
        ];
    }
}

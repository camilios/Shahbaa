<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class BookingRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            $userIdRules = $this->user()?->isAdmin()
                ? ['required', 'integer', Rule::exists('users', 'id')->where('role', 'customer')]
                : ['prohibited'];

            return [
                'user_id' => $userIdRules,
                'trip_id' => 'required|exists:trips,id',
                'pickup_checkpoint_id' => 'required|exists:checkpoints,id',
                'dropoff_checkpoint_id' => 'required|exists:checkpoints,id',
                'seats_count' => 'required|integer|min:1|max:50',
                'seat_numbers' => 'sometimes|required|array|min:1|max:50',
                'seat_numbers.*' => 'required|string|distinct|max:20',
                'status' => 'nullable|string|max:100',
            ];
        }

        return [
            'user_id' => 'prohibited',
            'driver_id' => 'prohibited',
            'trip_id' => 'prohibited',
            'pickup_checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'dropoff_checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'seats_count' => 'prohibited',
            'status' => 'nullable|string|max:100',
        ];
    }
}

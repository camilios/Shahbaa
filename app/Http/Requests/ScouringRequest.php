<?php

namespace App\Http\Requests;

class ScouringRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'driver_checkpoint_log_id' => 'required|exists:driver_checkpoint_logs,id',
                'customer_id' => 'required|exists:users,id',
                'booking_id' => 'required|exists:bookings,id',
                'points' => 'nullable|integer|min:0',
            ];
        }

        return [
            'driver_checkpoint_log_id' => 'sometimes|required|exists:driver_checkpoint_logs,id',
            'customer_id' => 'sometimes|required|exists:users,id',
            'booking_id' => 'sometimes|required|exists:bookings,id',
            'points' => 'nullable|integer|min:0',
        ];
    }
}

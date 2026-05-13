<?php

namespace App\Http\Requests;

class DriverCheckpointLogRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'driver_id' => 'required|exists:users,id',
                'trip_id' => 'required|exists:trips,id',
                'checkpoint_id' => 'required|exists:checkpoints,id',
                'scanned_at' => 'nullable|date',
            ];
        }

        return [
            'driver_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'checkpoint_id' => 'sometimes|required|exists:checkpoints,id',
            'scanned_at' => 'nullable|date',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GuestBookingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:50'],
            'guest_gender' => ['required', Rule::in(['male', 'female'])],
            'guest_national_number' => ['nullable', 'string', 'max:255'],
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'pickup_checkpoint_id' => ['required', 'integer', 'exists:checkpoints,id'],
            'dropoff_checkpoint_id' => ['required', 'integer', 'different:pickup_checkpoint_id', 'exists:checkpoints,id'],
            'seats_count' => ['required', 'integer', 'min:1', 'max:50'],
            'seat_numbers' => ['sometimes', 'required', 'array', 'min:1', 'max:50'],
            'seat_numbers.*' => ['required', 'string', 'distinct', 'max:20'],
        ];
    }
}

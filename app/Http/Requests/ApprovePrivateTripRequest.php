<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ApprovePrivateTripRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'driver_id' => ['required', Rule::exists('users', 'id')->where('role', 'driver')->where('status', 'active')],
            'checkpoint_ids' => ['required', 'array', 'min:2'],
            'checkpoint_ids.*' => ['required', 'integer', 'distinct', 'exists:checkpoints,id'],
            'point_price' => ['nullable', 'numeric', 'min:0'],
            'money_price' => ['nullable', 'numeric', 'min:0'],
            'departure_date' => ['required', 'date'],
            'arrival_date' => ['required', 'date', 'after:departure_date'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}

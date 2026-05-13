<?php

namespace App\Http\Requests;

class RatingRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'customer_id' => 'required|exists:users,id',
                'trip_id' => 'required|exists:trips,id',
                'rate' => 'required|integer|min:0|max:5',
                'comment' => 'nullable|string',
            ];
        }

        return [
            'customer_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'rate' => 'nullable|integer|min:0|max:5',
            'comment' => 'nullable|string',
        ];
    }
}

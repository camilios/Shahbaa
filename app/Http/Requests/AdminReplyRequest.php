<?php

namespace App\Http\Requests;

class AdminReplyRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['reply' => ['required', 'string', 'max:5000']];
    }
}

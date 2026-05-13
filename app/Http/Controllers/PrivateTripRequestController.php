<?php

namespace App\Http\Controllers;

use App\Models\PrivateTripRequest;
use Illuminate\Http\Request;

class PrivateTripRequestController extends Controller
{
    public function index()
    {
        return PrivateTripRequest::with('user')->paginate(20);
    }

    public function show(PrivateTripRequest $privateTripRequest)
    {
        return $privateTripRequest->load('user');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_location' => 'required|string|max:255',
            'to_location' => 'required|string|max:255',
            'status' => 'nullable|string|max:100',
        ]);

        return PrivateTripRequest::create($data);
    }

    public function update(Request $request, PrivateTripRequest $privateTripRequest)
    {
        $data = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
        ]);

        $privateTripRequest->update($data);

        return $privateTripRequest;
    }

    public function destroy(PrivateTripRequest $privateTripRequest)
    {
        $privateTripRequest->delete();

        return response()->noContent();
    }
}

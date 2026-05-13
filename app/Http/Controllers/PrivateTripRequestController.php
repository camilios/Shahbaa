<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrivateTripRequestRequest;
use App\Models\PrivateTripRequest;

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

    public function store(PrivateTripRequestRequest $request)
    {
        return PrivateTripRequest::create($request->validated());
    }

    public function update(PrivateTripRequestRequest $request, PrivateTripRequest $privateTripRequest)
    {
        $privateTripRequest->update($request->validated());

        return $privateTripRequest;
    }

    public function destroy(PrivateTripRequest $privateTripRequest)
    {
        $privateTripRequest->delete();

        return response()->noContent();
    }
}

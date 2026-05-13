<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverRequestRequest;
use App\Models\DriverRequest;

class DriverRequestController extends Controller
{
    public function index()
    {
        return DriverRequest::with(['driver', 'trip'])->paginate(20);
    }

    public function show(DriverRequest $driverRequest)
    {
        return $driverRequest->load(['driver', 'trip']);
    }

    public function store(DriverRequestRequest $request)
    {
        return DriverRequest::create($request->validated());
    }

    public function update(DriverRequestRequest $request, DriverRequest $driverRequest)
    {
        $driverRequest->update($request->validated());

        return $driverRequest;
    }

    public function destroy(DriverRequest $driverRequest)
    {
        $driverRequest->delete();

        return response()->noContent();
    }
}

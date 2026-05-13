<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScouringRequest;
use App\Models\Scouring;

class ScouringController extends Controller
{
    public function index()
    {
        return Scouring::with(['driverCheckpointLog', 'customer', 'booking'])->paginate(20);
    }

    public function show(Scouring $scouring)
    {
        return $scouring->load(['driverCheckpointLog', 'customer', 'booking']);
    }

    public function store(ScouringRequest $request)
    {
        return Scouring::create($request->validated());
    }

    public function update(ScouringRequest $request, Scouring $scouring)
    {
        $scouring->update($request->validated());

        return $scouring;
    }

    public function destroy(Scouring $scouring)
    {
        $scouring->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComplaintRequest;
use App\Models\Complaint;

class ComplaintController extends Controller
{
    public function index()
    {
        return Complaint::with('customer')->paginate(20);
    }

    public function show(Complaint $complaint)
    {
        return $complaint->load('customer');
    }

    public function store(ComplaintRequest $request)
    {
        return Complaint::create($request->validated());
    }

    public function update(ComplaintRequest $request, Complaint $complaint)
    {
        $complaint->update($request->validated());

        return $complaint;
    }

    public function destroy(Complaint $complaint)
    {
        $complaint->delete();

        return response()->noContent();
    }
}

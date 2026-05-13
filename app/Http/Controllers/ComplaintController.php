<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:users,id',
            'comment' => 'required|string',
            'status' => 'nullable|string|max:100',
        ]);

        return Complaint::create($data);
    }

    public function update(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'customer_id' => 'sometimes|required|exists:users,id',
            'comment' => 'nullable|string',
            'status' => 'nullable|string|max:100',
        ]);

        $complaint->update($data);

        return $complaint;
    }

    public function destroy(Complaint $complaint)
    {
        $complaint->delete();

        return response()->noContent();
    }
}

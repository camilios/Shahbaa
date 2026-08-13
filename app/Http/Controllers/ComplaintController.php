<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReplyRequest;
use App\Http\Requests\ComplaintRequest;
use App\Models\Complaint;

class ComplaintController extends Controller
{
    public function index()
    {
        return Complaint::with(['customer', 'repliedBy'])->paginate(20);
    }

    public function show(Complaint $complaint)
    {
        return $complaint->load(['customer', 'repliedBy']);
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

    public function reply(AdminReplyRequest $request, Complaint $complaint)
    {
        $complaint->update([
            'admin_reply' => $request->validated('reply'),
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
            'status' => 'answered',
        ]);

        return $complaint->load(['customer', 'repliedBy']);
    }
}

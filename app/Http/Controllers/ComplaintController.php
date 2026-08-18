<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReplyRequest;
use App\Http\Requests\ComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function mine(Request $request)
    {
        return Complaint::query()
            ->where('customer_id', $request->user()->id)
            ->with('repliedBy')
            ->latest('id')
            ->paginate(20);
    }

    public function storeForCurrentUser(Request $request)
    {
        $data = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        return Complaint::create([
            ...$data,
            'customer_id' => $request->user()->id,
            'status' => 'pending',
        ]);
    }

    public function updateForCurrentUser(Request $request, Complaint $complaint)
    {
        abort_unless($complaint->customer_id === $request->user()->id, 403);
        $complaint->update($request->validate([
            'comment' => ['required', 'string'],
        ]));

        return $complaint;
    }

    public function destroyForCurrentUser(Request $request, Complaint $complaint)
    {
        abort_unless($complaint->customer_id === $request->user()->id, 403);
        $complaint->delete();

        return response()->noContent();
    }

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

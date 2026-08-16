<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReplyRequest;
use App\Http\Requests\ComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index()
    {
        $complaint = Complaint::where('customer_id' , auth('sanctum')->user()->id)->get();
        return response()->json($complaint);
    }

    public function show(Complaint $complaint)
    {
        return $complaint->load(['customer', 'repliedBy']);
    }

    public function store(Request $request)
    {
        $complaint = Complaint::create([
            'customer_id' => auth('sanctum')->user()->id,
            'comment' => $request->comment,
            'admin_reply' => null,
            'replied_by' => null,
            'replied_at' => null,
            'status' => 'pending',
        ]);

        return response()->json($complaint, 201);
    }

    public function update(Request $request)
    {
        $complaint = Complaint::findOrFail($request->id);

        if ($request->has('comment')) {
                $complaint->comment = $request->comment;
                        }

             $complaint->save();

        return response()->json([
            'message' => 'Complaint updated successfully.',
            'complaint' => $complaint]
            , 200);

    }

    public function destroy(Request $request)
    {

      
      $complaint = Complaint::find($request->id);

      if (!$complaint) {
        return response()->json([
        'message' => 'Complaint not found.'
         ], 404);
}

       $complaint->delete();

      return response()->json([
      'message' => 'Complaint deleted successfully.'
       ], 200);
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

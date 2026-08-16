<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReplyRequest;
use App\Http\Requests\RatingRequest;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index()
    {
         $rate = Rating::where('customer_id' , auth('sanctum')->user()->id)->get();
        return response()->json($rate);
    }

    public function show(Request $request)
    {
        $query = Rating::query();

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('trip_id')) {
            $query->where('trip_id', $request->trip_id);
        }

        return $query->with(['customer', 'trip', 'repliedBy'])->paginate(20);
    }

    public function store(Request $request)
    {
         Rating::create([
                'trip_id' => $request->trip_id,
                'customer_id' => auth('sanctum')->user()->id,
                'rate' => $request->rate,
                'comment' => $request->comment,
                'admin_reply' => null,
                'replied_by' => null,
                'replied_at' => null,
            ]);

        return response()->json(['message' => 'Rating submitted successfully.'], 201);
    }

    public function update(Request $request)
    {
 
        $rating = Rating::findOrFail($request->id);

        if ($request->has('rate')) {
                $rating->rate = $request->rate;
                        }

        if ($request->has('comment')) {
                $rating->comment = $request->comment;
                        }

             $rating->save();

        return response()->json([
            'message' => 'Rating updated successfully.',
            'rating' => $rating]
            , 200);
    }

    public function destroy(Request $request)
    {
        $rating = Rating::findOrFail($request->id);

        $rating->delete();

        return response()->json([
       'message' => 'Rating deleted successfully.'
        ], 200);
    }

    public function reply(AdminReplyRequest $request, Rating $rating)
    {
        $rating->update([
            'admin_reply' => $request->validated('reply'),
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
        ]);

        return $rating->load(['customer', 'trip', 'repliedBy']);
    }
}

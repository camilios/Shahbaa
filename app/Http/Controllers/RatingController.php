<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReplyRequest;
use App\Http\Requests\RatingRequest;
use App\Models\Rating;

class RatingController extends Controller
{
    public function index()
    {
        return Rating::with(['customer', 'trip', 'repliedBy'])->paginate(20);
    }

    public function show(Rating $rating)
    {
        return $rating->load(['customer', 'trip', 'repliedBy']);
    }

    public function store(RatingRequest $request)
    {
        return Rating::create($request->validated());
    }

    public function update(RatingRequest $request, Rating $rating)
    {
        $rating->update($request->validated());

        return $rating;
    }

    public function destroy(Rating $rating)
    {
        $rating->delete();

        return response()->noContent();
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

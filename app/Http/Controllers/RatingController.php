<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReplyRequest;
use App\Http\Requests\RatingRequest;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function mine(Request $request)
    {
        return Rating::query()
            ->where('customer_id', $request->user()->id)
            ->with(['trip', 'repliedBy'])
            ->latest('id')
            ->paginate(20);
    }

    public function storeForCurrentUser(Request $request)
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'rate' => ['required', 'integer', 'min:0', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        return Rating::create([
            ...$data,
            'customer_id' => $request->user()->id,
        ]);
    }

    public function updateForCurrentUser(Request $request, Rating $rating)
    {
        abort_unless($rating->customer_id === $request->user()->id, 403);
        $rating->update($request->validate([
            'rate' => ['sometimes', 'required', 'integer', 'min:0', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string'],
        ]));

        return $rating;
    }

    public function destroyForCurrentUser(Request $request, Rating $rating)
    {
        abort_unless($rating->customer_id === $request->user()->id, 403);
        $rating->delete();

        return response()->noContent();
    }

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

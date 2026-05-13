<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index()
    {
        return Rating::with(['customer', 'trip'])->paginate(20);
    }

    public function show(Rating $rating)
    {
        return $rating->load(['customer', 'trip']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:users,id',
            'trip_id' => 'required|exists:trips,id',
            'rate' => 'required|integer|min:0|max:5',
            'comment' => 'nullable|string',
        ]);

        return Rating::create($data);
    }

    public function update(Request $request, Rating $rating)
    {
        $data = $request->validate([
            'customer_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'rate' => 'nullable|integer|min:0|max:5',
            'comment' => 'nullable|string',
        ]);

        $rating->update($data);

        return $rating;
    }

    public function destroy(Rating $rating)
    {
        $rating->delete();

        return response()->noContent();
    }
}

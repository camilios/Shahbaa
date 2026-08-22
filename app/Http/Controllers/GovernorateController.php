<?php

namespace App\Http\Controllers;

use App\Http\Requests\GovernorateRequest;
use App\Models\Governorate;
use Illuminate\Validation\ValidationException;

class GovernorateController extends Controller
{
    public function index()
    {
        return Governorate::query()
            ->withCount(['checkpoints', 'sourceTrips', 'destinationTrips'])
            ->orderBy('name')
            ->get();
    }

    public function store(GovernorateRequest $request)
    {
        return response()->json(Governorate::create($request->validated()), 201);
    }

    public function update(GovernorateRequest $request, Governorate $governorate)
    {
        $governorate->update($request->validated());

        return $governorate->fresh();
    }

    public function destroy(Governorate $governorate)
    {
        if ($governorate->checkpoints()->exists()
            || $governorate->sourceTrips()->exists()
            || $governorate->destinationTrips()->exists()) {
            throw ValidationException::withMessages([
                'governorate' => ['لا يمكن حذف محافظة مرتبطة بنقاط توقف أو رحلات.'],
            ]);
        }

        $governorate->delete();

        return response()->noContent();
    }
}

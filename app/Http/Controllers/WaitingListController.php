<?php

namespace App\Http\Controllers;

use App\Models\WaitingList;
use Illuminate\Http\Request;

class WaitingListController extends Controller
{
    public function index()
    {
        return WaitingList::with(['user', 'trip'])->paginate(20);
    }

    public function show(WaitingList $waitingList)
    {
        return $waitingList->load(['user', 'trip']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'trip_id' => 'required|exists:trips,id',
            'status' => 'nullable|string|max:100',
        ]);

        return WaitingList::create($data);
    }

    public function update(Request $request, WaitingList $waitingList)
    {
        $data = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'trip_id' => 'sometimes|required|exists:trips,id',
            'status' => 'nullable|string|max:100',
        ]);

        $waitingList->update($data);

        return $waitingList;
    }

    public function destroy(WaitingList $waitingList)
    {
        $waitingList->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaitingListRequest;
use App\Models\WaitingList;

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

    public function store(WaitingListRequest $request)
    {
        return WaitingList::create($request->validated());
    }

    public function update(WaitingListRequest $request, WaitingList $waitingList)
    {
        $waitingList->update($request->validated());

        return $waitingList;
    }

    public function destroy(WaitingList $waitingList)
    {
        $waitingList->delete();

        return response()->noContent();
    }
}

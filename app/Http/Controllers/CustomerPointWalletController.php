<?php

namespace App\Http\Controllers;

use App\Services\PointWalletService;
use Illuminate\Http\Request;

class CustomerPointWalletController extends Controller
{
    public function show(Request $request, PointWalletService $walletService)
    {
        abort_unless(strtolower((string) $request->user()->role) === 'customer', 403);
        $wallet = $walletService->wallet($request->user());

        return response()->json([
            'wallet' => $wallet,
            'summary' => [
                'total_earned' => $wallet->transactions()->where('type', 'credit')->sum('amount'),
                'total_spent' => $wallet->transactions()->whereIn('type', ['payment', 'debit'])->sum('amount'),
            ],
        ]);
    }

    public function transactions(Request $request, PointWalletService $walletService)
    {
        abort_unless(strtolower((string) $request->user()->role) === 'customer', 403);
        $wallet = $walletService->wallet($request->user());

        return $wallet->transactions()
            ->with(['booking:id,trip_id,status,payment_status', 'scouring:id,driver_checkpoint_log_id'])
            ->latest('id')
            ->paginate(20);
    }
}

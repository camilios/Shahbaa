<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PointAuditLog;
use App\Models\PointTransaction;
use App\Models\PointWallet;
use App\Models\Scouring;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PointWalletService
{
    public function creditForConfirmedBooking(Booking $booking): ?PointTransaction
    {
        $booking->loadMissing(['trip', 'user']);
        $amount = (int) ($booking->trip?->earned_points ?? 0);

        if ($amount <= 0 || ! $booking->user) {
            return null;
        }

        $transaction = $this->change(
            $booking->user,
            'credit',
            $amount,
            "Points earned from confirmed booking #{$booking->id}.",
            "booking:{$booking->id}:confirmation-reward",
            null,
            $booking->id,
            ['trip_id' => $booking->trip_id, 'reason' => 'booking_confirmed']
        );

        if ($transaction->wasRecentlyCreated) {
            PointAuditLog::create([
                'actor_id' => auth()->id(),
                'customer_id' => $booking->user_id,
                'booking_id' => $booking->id,
                'action' => 'granted',
                'points_before' => $transaction->balance_before,
                'points_after' => $transaction->balance_after,
                'points_delta' => $amount,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'context' => ['trip_id' => $booking->trip_id, 'reason' => 'booking_confirmed'],
            ]);
        }

        return $transaction;
    }

    public function wallet(User $user, bool $lock = false): PointWallet
    {
        PointWallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);
        $query = PointWallet::where('user_id', $user->id);

        return $lock ? $query->lockForUpdate()->firstOrFail() : $query->firstOrFail();
    }

    public function creditFromScouring(Scouring $scouring): void
    {
        $this->change(
            User::findOrFail($scouring->customer_id),
            'credit',
            (int) $scouring->points,
            "Points earned from checkpoint log #{$scouring->driver_checkpoint_log_id}.",
            "scouring:{$scouring->id}:created",
            $scouring
        );
    }

    public function adjustFromScouring(Scouring $scouring, int $before, int $after): void
    {
        $delta = $after - $before;
        if ($delta === 0) return;

        $this->change(
            User::findOrFail($scouring->customer_id),
            $delta > 0 ? 'credit' : 'debit',
            abs($delta),
            "Points adjustment for ledger entry #{$scouring->id}.",
            "scouring:{$scouring->id}:updated:{$scouring->updated_at?->getTimestamp()}",
            $scouring
        );
    }

    public function revokeScouring(Scouring $scouring): void
    {
        $this->change(
            User::findOrFail($scouring->customer_id),
            'debit',
            (int) $scouring->points,
            "Points revoked with ledger entry #{$scouring->id}.",
            "scouring:{$scouring->id}:deleted",
            $scouring
        );
    }

    public function debitForBooking(User $user, int $amount, int $bookingId, string $reference): PointTransaction
    {
        return $this->change(
            $user,
            'payment',
            $amount,
            "Points payment for booking #{$bookingId}.",
            "booking:{$bookingId}:points-payment",
            null,
            $bookingId,
            ['payment_reference' => $reference]
        );
    }

    private function change(
        User $user,
        string $type,
        int $amount,
        string $description,
        string $idempotencyKey,
        ?Scouring $scouring = null,
        ?int $bookingId = null,
        array $metadata = []
    ): PointTransaction {
        return DB::transaction(function () use ($user, $type, $amount, $description, $idempotencyKey, $scouring, $bookingId, $metadata) {
            $existing = PointTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing;

            $wallet = $this->wallet($user, true);
            $before = $wallet->balance;
            $isCredit = $type === 'credit';

            if (! $isCredit && $before < $amount) {
                throw ValidationException::withMessages(['points' => ['Insufficient point balance.']]);
            }

            $after = $isCredit ? $before + $amount : $before - $amount;
            $wallet->update(['balance' => $after]);

            return PointTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'booking_id' => $bookingId ?? $scouring?->booking_id,
                'scouring_id' => $scouring?->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata,
            ]);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->whereIn('bookings.status', ['confirmed', 'booked'])
            ->where('trips.earned_points', '>', 0)
            ->select([
                'bookings.id as booking_id',
                'bookings.user_id',
                'bookings.trip_id',
                'trips.earned_points',
            ])
            ->orderBy('bookings.id')
            ->chunk(100, function ($bookings): void {
                foreach ($bookings as $booking) {
                    DB::transaction(function () use ($booking): void {
                        $idempotencyKey = "booking:{$booking->booking_id}:confirmation-reward";

                        if (DB::table('point_transactions')->where('idempotency_key', $idempotencyKey)->exists()) {
                            return;
                        }

                        $wallet = DB::table('point_wallets')
                            ->where('user_id', $booking->user_id)
                            ->lockForUpdate()
                            ->first();

                        if (! $wallet) {
                            $walletId = DB::table('point_wallets')->insertGetId([
                                'user_id' => $booking->user_id,
                                'balance' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $balanceBefore = 0;
                        } else {
                            $walletId = $wallet->id;
                            $balanceBefore = (int) $wallet->balance;
                        }

                        $amount = (int) $booking->earned_points;
                        $balanceAfter = $balanceBefore + $amount;

                        DB::table('point_wallets')->where('id', $walletId)->update([
                            'balance' => $balanceAfter,
                            'updated_at' => now(),
                        ]);

                        DB::table('point_transactions')->insert([
                            'wallet_id' => $walletId,
                            'user_id' => $booking->user_id,
                            'booking_id' => $booking->booking_id,
                            'scouring_id' => null,
                            'type' => 'credit',
                            'amount' => $amount,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'description' => "Points earned from confirmed booking #{$booking->booking_id}.",
                            'idempotency_key' => $idempotencyKey,
                            'metadata' => json_encode([
                                'trip_id' => $booking->trip_id,
                                'reason' => 'booking_confirmed',
                                'backfilled' => true,
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('point_audit_logs')->insert([
                            'scouring_id' => null,
                            'actor_id' => null,
                            'customer_id' => $booking->user_id,
                            'booking_id' => $booking->booking_id,
                            'action' => 'granted',
                            'points_before' => $balanceBefore,
                            'points_after' => $balanceAfter,
                            'points_delta' => $amount,
                            'ip_address' => null,
                            'user_agent' => null,
                            'context' => json_encode([
                                'trip_id' => $booking->trip_id,
                                'reason' => 'booking_confirmed',
                                'backfilled' => true,
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    });
                }
            });
    }

    public function down(): void
    {
        // Rewards are financial-like ledger entries and are intentionally not
        // removed automatically during rollback.
    }
};

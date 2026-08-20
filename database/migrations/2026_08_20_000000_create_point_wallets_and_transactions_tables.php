<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('point_wallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('scouring_id')->nullable()->constrained('scourings')->nullOnDelete();
            $table->string('type', 30);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('description')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('status');
            $table->string('payment_status', 30)->default('unpaid')->after('payment_method');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('paid_amount');
            $table->string('payment_reference')->nullable()->unique()->after('paid_at');
        });

        // Preserve points that were granted before wallets were introduced.
        $now = now();
        DB::table('scourings')->orderBy('id')->get()->each(function ($scouring) use ($now) {
            $wallet = DB::table('point_wallets')->where('user_id', $scouring->customer_id)->first();
            if (! $wallet) {
                $walletId = DB::table('point_wallets')->insertGetId([
                    'user_id' => $scouring->customer_id,
                    'balance' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $balance = 0;
            } else {
                $walletId = $wallet->id;
                $balance = (int) $wallet->balance;
            }

            $after = $balance + (int) $scouring->points;
            DB::table('point_wallets')->where('id', $walletId)->update(['balance' => $after, 'updated_at' => $now]);
            DB::table('point_transactions')->insert([
                'wallet_id' => $walletId,
                'user_id' => $scouring->customer_id,
                'booking_id' => $scouring->booking_id,
                'scouring_id' => $scouring->id,
                'type' => 'credit',
                'amount' => $scouring->points,
                'balance_before' => $balance,
                'balance_after' => $after,
                'description' => "Imported points from ledger entry #{$scouring->id}.",
                'idempotency_key' => "scouring:{$scouring->id}:created",
                'metadata' => null,
                'created_at' => $scouring->created_at ?? $now,
                'updated_at' => $scouring->updated_at ?? $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);
            $table->dropColumn(['payment_method', 'payment_status', 'paid_amount', 'paid_at', 'payment_reference']);
        });
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('point_wallets');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'user_id', 'booking_id', 'scouring_id', 'type', 'amount',
        'balance_before', 'balance_after', 'description', 'idempotency_key', 'metadata',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array'];
    }

    public function wallet() { return $this->belongsTo(PointWallet::class, 'wallet_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function scouring() { return $this->belongsTo(Scouring::class); }
}

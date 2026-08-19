<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointAuditLog extends Model
{
    protected $fillable = [
        'scouring_id',
        'actor_id',
        'customer_id',
        'booking_id',
        'action',
        'points_before',
        'points_after',
        'points_delta',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function scouring()
    {
        return $this->belongsTo(Scouring::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booking_source',
        'guest_name',
        'guest_phone',
        'guest_gender',
        'guest_national_number',
        'driver_id',
        'trip_id',
        'pickup_checkpoint_id',
        'dropoff_checkpoint_id',
        'seats_count',
        'status',
        'payment_method',
        'payment_status',
        'paid_amount',
        'paid_at',
        'payment_reference',
        'boarded_at',
    ];

    protected function casts(): array
    {
        return [
            'boarded_at' => 'datetime',
            'paid_at' => 'datetime',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function pickupCheckpoint()
    {
        return $this->belongsTo(Checkpoint::class, 'pickup_checkpoint_id');
    }

    public function dropoffCheckpoint()
    {
        return $this->belongsTo(Checkpoint::class, 'dropoff_checkpoint_id');
    }

    public function scouring()
    {
        return $this->hasOne(Scouring::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }
}

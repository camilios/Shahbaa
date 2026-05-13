<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scouring extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_checkpoint_log_id',
        'customer_id',
        'booking_id',
        'points',
    ];

    public function driverCheckpointLog()
    {
        return $this->belongsTo(DriverCheckpointLog::class);
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

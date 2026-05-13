<?php

namespace App\Models;

use App\Models\Booking;
use App\Models\DriverCheckpointLog;
use App\Models\TripCheckpoint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'governorate',
    ];

    public function tripCheckpoints()
    {
        return $this->hasMany(TripCheckpoint::class);
    }

    public function pickupBookings()
    {
        return $this->hasMany(Booking::class, 'pickup_checkpoint_id');
    }

    public function dropoffBookings()
    {
        return $this->hasMany(Booking::class, 'dropoff_checkpoint_id');
    }

    public function driverCheckpointLogs()
    {
        return $this->hasMany(DriverCheckpointLog::class);
    }
}

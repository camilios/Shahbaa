<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'governorate',
        'governorate_id',
    ];

    public function tripCheckpoints()
    {
        return $this->hasMany(TripCheckpoint::class);
    }

    public function governorateRelation()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
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

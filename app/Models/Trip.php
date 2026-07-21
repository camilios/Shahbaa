<?php

namespace App\Models;

use App\Models\Booking;
use App\Models\DriverCheckpointLog;
use App\Models\DriverRequest;
use App\Models\Rating;
use App\Models\Seat;
use App\Models\TripCheckpoint;
use App\Models\WaitingList;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'type',
        'point_price',
        'money_price',
        'status',
        'departure_date',
        'arrival_date',
        'total_seats',
        'available_seats',
        'earned_points',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    public function checkpoints()
    {
        return $this->hasMany(TripCheckpoint::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function waitingList()
    {
        return $this->hasMany(WaitingList::class);
    }

    public function driverRequests()
    {
        return $this->hasMany(DriverRequest::class);
    }

    public function driverCheckpointLogs()
    {
        return $this->hasMany(DriverCheckpointLog::class);
    }

    public function objections()
    {
        return $this->hasMany(TripObjection::class);
    }
}

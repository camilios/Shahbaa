<?php

namespace App\Models;

use App\Models\Scouring;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverCheckpointLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'trip_id',
        'checkpoint_id',
        'scanned_at',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function checkpoint()
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function scouring()
    {
        return $this->hasOne(Scouring::class);
    }
}

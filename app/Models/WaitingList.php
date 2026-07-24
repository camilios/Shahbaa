<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trip_id',
        'pickup_checkpoint_id',
        'dropoff_checkpoint_id',
        'seats_count',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
}

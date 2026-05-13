<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripCheckpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'checkpoint_id',
        'description',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function checkpoint()
    {
        return $this->belongsTo(Checkpoint::class);
    }
}

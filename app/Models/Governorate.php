<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function checkpoints()
    {
        return $this->hasMany(Checkpoint::class);
    }

    public function sourceTrips()
    {
        return $this->hasMany(Trip::class, 'source_governorate_id');
    }

    public function destinationTrips()
    {
        return $this->hasMany(Trip::class, 'destination_governorate_id');
    }
}

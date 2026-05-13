<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivateTripRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_location',
        'to_location',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

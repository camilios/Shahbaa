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
        'rejection_reason',
        'rejected_by',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'rejected_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}

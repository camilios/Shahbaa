<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'comment',
        'status',
        'admin_reply',
        'replied_by',
        'replied_at',
    ];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointWallet extends Model
{
    protected $fillable = ['user_id', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(PointTransaction::class, 'wallet_id');
    }
}

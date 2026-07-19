<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function driverBookings()
    {
        return $this->hasMany(Booking::class, 'driver_id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'customer_id');
    }

    public function privateTripRequests()
    {
        return $this->hasMany(PrivateTripRequest::class);
    }

    public function waitingList()
    {
        return $this->hasMany(WaitingList::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class, 'customer_id');
    }

    public function driverRequests()
    {
        return $this->hasMany(DriverRequest::class, 'driver_id');
    }

    public function driverCheckpointLogs()
    {
        return $this->hasMany(DriverCheckpointLog::class, 'driver_id');
    }
}

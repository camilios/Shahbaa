<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        'national_number',
        'father_name',
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

    /**
     * Determine whether the user is a driver.
     */
    public function isDriver(): bool
    {
        return strtolower((string) $this->role) === 'driver';
    }

    /**
     * Determine whether the user may use administrative features.
     */
    public function isAdmin(): bool
    {
        return strtolower((string) $this->role) === 'admin';
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

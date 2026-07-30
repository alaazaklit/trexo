<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends \TCG\Voyager\Models\User implements JWTSubject
{
    use Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'password',
        'type',
        'is_verified',
        'account_status',
        'status_reason',
        'status_changed_at',
        'status_changed_by',
        'fcm_token',
        'is_available',
        'latitude',
        'longitude',
        'heading',
        'speed_kmh',
        'last_seen_at',
        'is_simulated',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'status_changed_at' => 'datetime',
        'is_available' => 'boolean',
        'is_simulated' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'heading' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'last_seen_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function orders()
    {
        return $this->hasMany(\App\Order::class, 'user_id');
    }

    public function isBlocked(): bool
    {
        return $this->account_status !== 'active';
    }
}
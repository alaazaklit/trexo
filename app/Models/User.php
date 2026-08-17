<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends \TCG\Voyager\Models\User implements JWTSubject
{
    use HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'password',
        'type',
        'gender',
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
        'is_demo_account',
        'language',
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
        'is_demo_account' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'heading' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'last_seen_at' => 'datetime',
    ];

    // Voyager's own base model hardcodes newFactory() to its own internal
    // test-only factory class, which isn't shipped in vendor — that leaves
    // User::factory() broken for every consumer of this package unless
    // overridden here, the standard Laravel fix for a model that extends a
    // package base class with its own (non-functional) factory binding.
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

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

    public function refreshTokens()
    {
        return $this->hasMany(\App\Models\RefreshToken::class);
    }

    public function isBlocked(): bool
    {
        return $this->account_status !== 'active';
    }

    /**
     * Mirrors UsersController::normalizePhoneNumber() — strips everything
     * but digits and drops a leading Lebanese country code, so a phone
     * value from .env config matches what the app actually sends.
     */
    public static function normalizePhone(?string $phone): string
    {
        $digitsOnly = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digitsOnly, '961')) {
            $digitsOnly = substr($digitsOnly, 3);
        }

        return $digitsOnly;
    }

    /**
     * Scrubs PII in place (name/email/phone/password/avatar/fcm token) and
     * saves. Shared by the in-app delete-account API and the public
     * delete-account web page so both stay identical. Caller is still
     * responsible for revoking tokens and soft-deleting the row.
     */
    public function anonymize(): void
    {
        if ($this->avatar) {
            Storage::disk('public')->delete($this->avatar);
        }

        $this->name = 'Deleted user';
        $this->email = "deleted_{$this->id}_".time().'@deleted.local';
        $this->phone = "deleted_{$this->id}";
        $this->password = Hash::make(Str::random(40));
        $this->fcm_token = null;
        $this->api_token = null;
        $this->avatar = null;
        $this->is_available = false;
        $this->save();
    }
}

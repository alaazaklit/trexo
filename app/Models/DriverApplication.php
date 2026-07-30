<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverApplication extends Model
{
    public const STATUSES = ['pending', 'under_review', 'approved', 'rejected'];

    protected $fillable = [
        'driver_id',
        'full_name',
        'mobile_number',
        'whatsapp_number',
        'email',
        'city',
        'service_type',
        'national_id_number',
        'driving_license_number',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_year',
        'plate_number',
        'national_id_front_path',
        'driving_license_path',
        'vehicle_registration_path',
        'personal_photo_path',
        'vehicle_photo_path',
        'confirmed_information_correct',
        'agreed_terms',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confirmed_information_correct' => 'boolean',
        'agreed_terms' => 'boolean',
        'vehicle_year' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function notes(): HasMany
    {
        return $this->hasMany(DriverApplicationNote::class)->latest();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}

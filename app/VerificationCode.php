<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VerificationCode extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
        'type',
        'used',
    ];
}

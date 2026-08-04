<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $table = 'otps';

    protected $fillable = [
        'email',
        'phone',
        'otp',
        'type',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isValid(string $inputOtp): bool
    {
        if ($this->used_at !== null) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        return (string) $this->otp === (string) $inputOtp;
    }
}

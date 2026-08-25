<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    protected $table = 'drivers';

    protected $fillable = [
        'user_id',
        'branch_id',
        'name',
        'phone',
        'vehicle_type',
        'license_number',
        'license_image',
        'kyc_status',
        'reject_reason',
        'is_online',
        'status',
    ];

    protected $appends = [
        'license_image_url',
    ];

    public function getLicenseImageUrlAttribute(): ?string
    {
        if (!$this->license_image) {
            return null;
        }
        return str_starts_with($this->license_image, 'http')
            ? $this->license_image
            : asset('storage/' . $this->license_image);
    }

    protected $casts = [
        'is_online' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function declinedOrders()
    {
        return $this->hasMany(DriverDeclinedOrder::class);
    }

    public function locations(): HasMany
{
    return $this->hasMany(DriverLocation::class);
}
}
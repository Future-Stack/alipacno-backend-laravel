<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Auditable, SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'gender',
        'password',
        'avatar',
        'user_image',
        'user_type',
        'role_id',
        'email_verified_at',
        'phone_verified_at',
        'loyalty_points_balance',
        'status',
        'terms_accepted',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'user_image_url',
        'kyc_status',
        'is_online',
        'driver_status',
        'reject_reason',
        'branch_id',
        'latitude',
        'longitude',
    ];

    public function getLatitudeAttribute(): ?float
    {
        if (isset($this->attributes['latitude']) && $this->attributes['latitude'] !== null) {
            return (float) $this->attributes['latitude'];
        }

        $addr = $this->relationLoaded('defaultAddress') 
            ? $this->defaultAddress 
            : ($this->relationLoaded('address') ? $this->address : null);

        if ($addr && $addr->latitude !== null) {
            return (float) $addr->latitude;
        }

        if ($this->relationLoaded('addresses') && $this->addresses->isNotEmpty()) {
            $firstAddr = $this->addresses->first(fn($a) => $a->latitude !== null);
            if ($firstAddr && $firstAddr->latitude !== null) {
                return (float) $firstAddr->latitude;
            }
        }

        $addrVal = $this->defaultAddress()->value('latitude') ?? $this->addresses()->whereNotNull('latitude')->value('latitude');
        return $addrVal !== null ? (float) $addrVal : null;
    }

    public function getLongitudeAttribute(): ?float
    {
        if (isset($this->attributes['longitude']) && $this->attributes['longitude'] !== null) {
            return (float) $this->attributes['longitude'];
        }

        $addr = $this->relationLoaded('defaultAddress') 
            ? $this->defaultAddress 
            : ($this->relationLoaded('address') ? $this->address : null);

        if ($addr && $addr->longitude !== null) {
            return (float) $addr->longitude;
        }

        if ($this->relationLoaded('addresses') && $this->addresses->isNotEmpty()) {
            $firstAddr = $this->addresses->first(fn($a) => $a->longitude !== null);
            if ($firstAddr && $firstAddr->longitude !== null) {
                return (float) $firstAddr->longitude;
            }
        }

        $addrVal = $this->defaultAddress()->value('longitude') ?? $this->addresses()->whereNotNull('longitude')->value('longitude');
        return $addrVal !== null ? (float) $addrVal : null;
    }

    public function getBranchIdAttribute(): ?int
    {
        return $this->branchAdmin?->branch_id
            ?? $this->driver?->branch_id
            ?? ($this->attributes['branch_id'] ?? null);
    }

    public function getKycStatusAttribute(): ?string
    {
        return $this->driver?->kyc_status ?? ($this->user_type === 'driver' ? 'pending' : null);
    }

    public function getIsOnlineAttribute(): bool
    {
        return (bool) ($this->driver?->is_online ?? false);
    }

    public function getDriverStatusAttribute(): ?string
    {
        return $this->driver?->status ?? ($this->user_type === 'driver' ? 'available' : null);
    }

    public function getRejectReasonAttribute(): ?string
    {
        return $this->driver?->reject_reason;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $image = $this->avatar ?? $this->user_image;
        if (!$image) {
            return null;
        }
        return str_starts_with($image, 'http')
            ? $image
            : asset('storage/' . $image);
    }

    public function getUserImageUrlAttribute(): ?string
    {
        $image = $this->user_image ?? $this->avatar;
        if (!$image) {
            return null;
        }
        return str_starts_with($image, 'http')
            ? $image
            : asset('storage/' . $image);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted' => 'boolean',
        ];
    }

    // Relationships
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true)->latestOfMany();
    }

    public function address()
    {
        return $this->hasOne(UserAddress::class)->latestOfMany();
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function loyaltyPoints()
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function tableReservations()
    {
        return $this->hasMany(TableReservation::class);
    }

    public function callLogs()
    {
        return $this->hasMany(CallLog::class);
    }

    // Role & Permission Checks
    public function hasRole($roles): bool
    {
        if (is_string($roles)) {
            $roles = array_map('trim', explode(',', $roles));
        }

        $normalizedRequiredRoles = array_map(function ($r) {
            return strtolower(str_replace([' ', '-'], '_', trim($r)));
        }, (array) $roles);

        // Super Admin & Admin bypass check
        if ($this->user_type === 'super_admin' || $this->user_type === 'admin') {
            return true;
        }

        // Direct user_type matching
        $normalizedUserType = strtolower(str_replace([' ', '-'], '_', (string) $this->user_type));
        if (in_array($normalizedUserType, $normalizedRequiredRoles)) {
            return true;
        }

        // Driver check (via user_type, driver relation, or role)
        $wantsDriver = in_array('driver', $normalizedRequiredRoles) || in_array('delivery_driver', $normalizedRequiredRoles);
        if ($wantsDriver) {
            if ($this->user_type === 'driver') {
                return true;
            }
            if ($this->relationLoaded('driver') ? (bool) $this->driver : $this->driver()->exists()) {
                return true;
            }
        }

        // Branch Admin check
        $wantsBranchAdmin = in_array('branch_admin', $normalizedRequiredRoles) || in_array('branch_manager', $normalizedRequiredRoles);
        if ($wantsBranchAdmin && ($this->user_type === 'branch_admin' || $this->isBranchAdmin())) {
            return true;
        }

        // HQ Admin check
        $wantsHqAdmin = in_array('hq_admin', $normalizedRequiredRoles);
        if ($wantsHqAdmin && $this->user_type === 'hq_admin') {
            return true;
        }

        // Role-based matching from roles table
        $role = $this->role;
        if (!$role && $this->role_id) {
            $role = Role::find($this->role_id);
        }

        if ($role) {
            $roleName = strtolower(trim($role->name));
            $normalizedRoleName = strtolower(str_replace([' ', '-', '/'], '_', $role->name));

            if (in_array($roleName, $normalizedRequiredRoles) || in_array($normalizedRoleName, $normalizedRequiredRoles)) {
                return true;
            }

            // Role aliases
            if ($wantsDriver && str_contains($roleName, 'driver')) {
                return true;
            }
            if ($wantsBranchAdmin && (str_contains($roleName, 'branch') || str_contains($roleName, 'manager'))) {
                return true;
            }
            if (in_array('chef', $normalizedRequiredRoles) && (str_contains($roleName, 'chef') || str_contains($roleName, 'kitchen'))) {
                return true;
            }
            if (in_array('cashier', $normalizedRequiredRoles) && (str_contains($roleName, 'cashier') || str_contains($roleName, 'pos'))) {
                return true;
            }
            if (in_array('waiter', $normalizedRequiredRoles) && (str_contains($roleName, 'waiter') || str_contains($roleName, 'floor'))) {
                return true;
            }
            if (in_array('inventory_manager', $normalizedRequiredRoles) && str_contains($roleName, 'inventory')) {
                return true;
            }
            if (in_array('staff', $normalizedRequiredRoles) && !in_array($roleName, ['customer'])) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->user_type === 'super_admin' || $this->user_type === 'admin') {
            return true;
        }

        if (!$this->role) {
            return false;
        }

        return $this->role->permissions->contains('name', $permission);
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin' || $this->user_type === 'admin';
    }

    public function isBranchAdmin(): bool
    {
        return $this->user_type === 'branch_admin' || ($this->role && $this->role->name === 'Branch Manager');
    }

    public function isCustomer(): bool
    {
        return $this->user_type === 'customer';
    }

    public function branchAdmin(): HasOne
    {
        return $this->hasOne(BranchAdmin::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }
}

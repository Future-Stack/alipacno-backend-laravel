<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'user_image',
        'user_type',
        'role_id',
        'email_verified_at',
        'phone_verified_at',
        'loyalty_points_balance',
        'status',
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
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        return str_starts_with($this->avatar, 'http')
            ? $this->avatar
            : asset('storage/' . $this->avatar);
    }

    public function getUserImageUrlAttribute(): ?string
    {
        if (!$this->user_image) {
            return null;
        }
        return str_starts_with($this->user_image, 'http')
            ? $this->user_image
            : asset('storage/' . $this->user_image);
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

    // Role & Permission Checks
    public function hasRole($roles): bool
    {
        if (is_string($roles)) {
            $roles = array_map('trim', explode(',', $roles));
        }

        $userTypeMatch = in_array($this->user_type, $roles);
        $roleNameMatch = $this->role && in_array($this->role->name, $roles);

        return $userTypeMatch || $roleNameMatch || $this->user_type === 'super_admin';
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
}

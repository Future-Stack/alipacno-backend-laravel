<?php

namespace App\Services;

use App\Models\BranchAdmin;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Staff;
use App\Models\User;

class ChatPermissionService
{
    /**
     * Determine if a sender can message a receiver (with optional order context).
     */
    public function canMessage(User $sender, User $receiver, ?Order $order = null): array
    {
        if ($sender->id === $receiver->id) {
            return [
                'allowed' => false,
                'reason' => 'You cannot start a conversation with yourself.',
            ];
        }

        // 1. Super Admin can message anyone and anyone can message Super Admin
        if ($this->isSuperAdmin($sender) || $this->isSuperAdmin($receiver)) {
            return ['allowed' => true];
        }

        $senderBranchId = $this->getUserBranchId($sender);
        $receiverBranchId = $this->getUserBranchId($receiver);

        // 2. Sender is Branch Admin / Manager
        if ($this->isBranchAdmin($sender)) {
            // Branch manager can message their branch drivers, staff, and customers
            if ($this->isDriver($receiver)) {
                if ($receiverBranchId && $senderBranchId && (int)$receiverBranchId === (int)$senderBranchId) {
                    return ['allowed' => true];
                }
                return [
                    'allowed' => false,
                    'reason' => 'You can only message drivers assigned to your branch.',
                ];
            }

            if ($this->isStaff($receiver)) {
                if ($receiverBranchId && $senderBranchId && (int)$receiverBranchId === (int)$senderBranchId) {
                    return ['allowed' => true];
                }
                return [
                    'allowed' => false,
                    'reason' => 'You can only message staff members of your branch.',
                ];
            }

            if ($this->isCustomer($receiver)) {
                // Branch admin can message customers who have ordered from this branch
                if ($senderBranchId) {
                    $hasBranchOrder = Order::where('user_id', $receiver->id)
                        ->where('branch_id', $senderBranchId)
                        ->exists();

                    if ($hasBranchOrder) {
                        return ['allowed' => true];
                    }
                }
                return ['allowed' => true]; // Allow branch admin to initiate support with customers
            }

            return ['allowed' => true];
        }

        // 3. Sender is Customer
        if ($this->isCustomer($sender)) {
            // Customer messaging Branch Admin
            if ($this->isBranchAdmin($receiver)) {
                return ['allowed' => true];
            }

            // Customer messaging Driver: Must be assigned to one of customer's active/recent orders
            if ($this->isDriver($receiver)) {
                $driverId = $receiver->driver?->id ?? Driver::where('user_id', $receiver->id)->value('id');
                $driverIdentifiers = array_values(array_filter([$driverId, $receiver->id]));

                $hasActiveDelivery = Order::where('user_id', $sender->id)
                    ->whereIn('assigned_driver_id', $driverIdentifiers)
                    ->whereIn('order_status', ['accepted', 'preparing', 'ready', 'out_for_delivery', 'completed'])
                    ->when($order, fn($q) => $q->where('id', $order->id))
                    ->exists();

                if ($hasActiveDelivery) {
                    return ['allowed' => true];
                }

                return [
                    'allowed' => false,
                    'reason' => 'You can only message the driver assigned to your active or recent delivery order.',
                ];
            }

            return [
                'allowed' => false,
                'reason' => 'Customers can only message their Branch Manager, assigned Delivery Driver, or Super Admin.',
            ];
        }

        // 4. Sender is Driver
        if ($this->isDriver($sender)) {
            // Driver messaging Branch Admin of their branch
            if ($this->isBranchAdmin($receiver)) {
                if (!$senderBranchId || !$receiverBranchId || (int)$senderBranchId === (int)$receiverBranchId) {
                    return ['allowed' => true];
                }
                return [
                    'allowed' => false,
                    'reason' => 'Drivers can only message Branch Managers of their assigned branch.',
                ];
            }

            // Driver messaging Customer of assigned order
            if ($this->isCustomer($receiver)) {
                $driverId = $sender->driver?->id ?? Driver::where('user_id', $sender->id)->value('id');
                $driverIdentifiers = array_values(array_filter([$driverId, $sender->id]));

                $hasActiveDelivery = Order::where('user_id', $receiver->id)
                    ->whereIn('assigned_driver_id', $driverIdentifiers)
                    ->whereIn('order_status', ['accepted', 'preparing', 'ready', 'out_for_delivery', 'completed'])
                    ->when($order, fn($q) => $q->where('id', $order->id))
                    ->exists();

                if ($hasActiveDelivery) {
                    return ['allowed' => true];
                }

                return [
                    'allowed' => false,
                    'reason' => 'Drivers can only message customers of orders currently assigned to them.',
                ];
            }

            return [
                'allowed' => false,
                'reason' => 'Drivers can only message their assigned Order Customer, Branch Manager, or Super Admin.',
            ];
        }

        // 5. Sender is Staff / Chef / Waiter
        if ($this->isStaff($sender)) {
            if ($this->isBranchAdmin($receiver) || $this->isStaff($receiver)) {
                if (!$senderBranchId || !$receiverBranchId || (int)$senderBranchId === (int)$receiverBranchId) {
                    return ['allowed' => true];
                }
            }

            return [
                'allowed' => false,
                'reason' => 'Staff members can only message their Branch Manager or fellow branch staff.',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Get list of contacts the user is allowed to message with filters and counts.
     */
    public function getAllowedContacts(User $user, ?string $search = null, ?string $role = null, ?int $roleId = null, ?int $branchId = null, int $perPage = 20)
    {
        $userBranchId = $this->getUserBranchId($user);

        // 1. Super Admin / HQ Admin: Can contact anyone
        if ($this->isSuperAdmin($user)) {
            $baseQuery = User::where('id', '!=', $user->id);
        }
        // 2. Branch Admin: Super Admins, Branch Staff, Branch Drivers, Branch Customers
        elseif ($this->isBranchAdmin($user)) {
            $superAdminIds = User::whereIn('user_type', ['super_admin', 'admin', 'hq_admin'])->pluck('id');

            $branchStaffIds = Staff::when($userBranchId, fn($q) => $q->where('branch_id', $userBranchId))
                ->join('users', 'staff.email', '=', 'users.email')
                ->pluck('users.id');

            $branchDriverUserIds = Driver::when($userBranchId, fn($q) => $q->where('branch_id', $userBranchId))
                ->whereNotNull('user_id')
                ->pluck('user_id');

            $branchCustomerIds = Order::when($userBranchId, fn($q) => $q->where('branch_id', $userBranchId))
                ->whereNotNull('user_id')
                ->pluck('user_id');

            $allowedIds = $superAdminIds
                ->merge($branchStaffIds)
                ->merge($branchDriverUserIds)
                ->merge($branchCustomerIds)
                ->unique()
                ->reject(fn($id) => $id == $user->id);

            $baseQuery = User::whereIn('id', $allowedIds);
        }
        // 3. Customer: Branch Managers, Assigned Drivers of their active/recent orders
        elseif ($this->isCustomer($user)) {
            $customerBranchIds = Order::where('user_id', $user->id)->pluck('branch_id')->filter()->unique();
            $branchAdminIds = $this->getBranchAdminUserIds($customerBranchIds);

            $assignedDriverIds = Order::where('user_id', $user->id)
                ->whereNotNull('assigned_driver_id')
                ->pluck('assigned_driver_id')
                ->filter()
                ->unique();

            $driverUserIdsFromDriversTable = Driver::whereIn('id', $assignedDriverIds)->whereNotNull('user_id')->pluck('user_id');
            $driverUserIdsFromUserId = Driver::whereIn('user_id', $assignedDriverIds)->pluck('user_id');
            $driverUserIdsDirect = User::whereIn('id', $assignedDriverIds)->get()
                ->filter(fn($u) => $this->isDriver($u))
                ->pluck('id');

            $driverUserIds = $driverUserIdsFromDriversTable
                ->merge($driverUserIdsFromUserId)
                ->merge($driverUserIdsDirect)
                ->unique();

            $allowedIds = $branchAdminIds
                ->merge($driverUserIds)
                ->unique()
                ->reject(fn($id) => $id == $user->id);

            $baseQuery = User::whereIn('id', $allowedIds);
        }
        // 4. Driver: Branch Manager of branch, Customers of assigned orders
        elseif ($this->isDriver($user)) {
            $branchAdminIds = $userBranchId ? $this->getBranchAdminUserIds($userBranchId) : collect();

            $driverId = $user->driver?->id ?? Driver::where('user_id', $user->id)->value('id');
            $driverIdentifiers = array_values(array_filter([$driverId, $user->id]));
            $customerIds = Order::whereIn('assigned_driver_id', $driverIdentifiers)->whereNotNull('user_id')->pluck('user_id');

            $allowedIds = $branchAdminIds
                ->merge($customerIds)
                ->unique()
                ->reject(fn($id) => $id == $user->id);

            $baseQuery = User::whereIn('id', $allowedIds);
        }
        // Fallback for Staff
        else {
            $superAdminIds = User::whereIn('user_type', ['super_admin', 'admin', 'hq_admin'])->pluck('id');
            $branchAdminIds = $userBranchId ? $this->getBranchAdminUserIds($userBranchId) : collect();

            $allowedIds = $superAdminIds->merge($branchAdminIds)->unique()->reject(fn($id) => $id == $user->id);

            $baseQuery = User::whereIn('id', $allowedIds);
        }

        // Calculate summary counts by role
        $countsQuery = clone $baseQuery;
        $allCount = (clone $countsQuery)->count();
        $superAdminCount = (clone $countsQuery)->whereIn('user_type', ['super_admin', 'admin', 'hq_admin'])->count();
        $branchAdminCount = (clone $countsQuery)->where(function ($q) {
            $q->where('user_type', 'branch_admin')->orWhereHas('branchAdmin');
        })->count();
        $driverCount = (clone $countsQuery)->where(function ($q) {
            $q->where('user_type', 'driver')->orWhereHas('driver');
        })->count();
        $staffCount = (clone $countsQuery)->where(function ($q) {
            $q->whereIn('user_type', ['staff', 'chef', 'waiter', 'cashier'])
              ->whereDoesntHave('driver')
              ->whereDoesntHave('branchAdmin');
        })->count();
        $customerCount = (clone $countsQuery)->where('user_type', 'customer')->count();

        $summaryCounts = [
            'all' => $allCount,
            'super_admin' => $superAdminCount,
            'branch_admin' => $branchAdminCount,
            'staff' => $staffCount,
            'driver' => $driverCount,
            'customer' => $customerCount,
        ];

        // Apply role / user_type filter
        if ($role) {
            $normalizedRole = strtolower(trim($role));
            if ($normalizedRole === 'super_admin' || $normalizedRole === 'admin' || $normalizedRole === 'hq_admin') {
                $baseQuery->whereIn('user_type', ['super_admin', 'admin', 'hq_admin']);
            } elseif ($normalizedRole === 'staff') {
                $baseQuery->whereIn('user_type', ['staff', 'chef', 'waiter', 'cashier'])
                    ->whereDoesntHave('driver')
                    ->whereDoesntHave('branchAdmin');
            } elseif ($normalizedRole === 'driver') {
                $baseQuery->where(function ($q) {
                    $q->where('user_type', 'driver')->orWhereHas('driver');
                });
            } elseif ($normalizedRole === 'branch_manager' || $normalizedRole === 'branch_admin') {
                $baseQuery->where(function ($q) {
                    $q->where('user_type', 'branch_admin')->orWhereHas('branchAdmin');
                });
            } else {
                $baseQuery->where('user_type', $normalizedRole);
            }
        }

        // Apply role_id filter
        if ($roleId) {
            $baseQuery->where('role_id', $roleId);
        }

        if ($branchId) {
            $baseQuery->where(function ($q) use ($branchId) {
                $q->whereHas('branchAdmin', fn($ba) => $ba->where('branch_id', $branchId))
                  ->orWhereHas('driver', fn($d) => $d->where('branch_id', $branchId));
            });
        }

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $paginated = $baseQuery->with(['role', 'driver', 'branchAdmin'])->latest()->paginate($perPage);

        return [
            'summary_counts' => $summaryCounts,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'data' => $paginated->getCollection()->map(function ($u) {
                $effectiveUserType = $this->isDriver($u) ? 'driver' : ($this->isBranchAdmin($u) ? 'branch_admin' : $u->user_type);
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'user_type' => $effectiveUserType,
                    'role_id' => $u->role_id,
                    'role_name' => $u->role?->name ?? ucfirst(str_replace('_', ' ', $effectiveUserType)),
                    'avatar' => $u->avatar_url ?? $u->user_image_url,
                    'branch_id' => $u->branch_id,
                    'is_online' => (bool)$u->is_online,
                ];
            }),
        ];
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin() || in_array($user->user_type, ['super_admin', 'admin', 'hq_admin']) || (method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'admin', 'hq_admin']));
    }

    public function isBranchAdmin(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return false;
        }
        return $user->isBranchAdmin() || in_array($user->user_type, ['branch_admin']) || (bool)$user->branchAdmin || (method_exists($user, 'hasRole') && $user->hasRole(['branch_admin', 'Branch Manager', 'branch_manager']));
    }

    public function isCustomer(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return false;
        }
        return $user->isCustomer() || $user->user_type === 'customer';
    }

    public function isDriver(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return false;
        }
        return $user->user_type === 'driver' || (bool)$user->driver || (method_exists($user, 'hasRole') && $user->hasRole(['driver', 'Delivery Driver']));
    }

    public function isStaff(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return false;
        }
        return in_array($user->user_type, ['staff', 'chef', 'waiter', 'cashier']) || (method_exists($user, 'hasRole') && $user->hasRole(['staff', 'Chef', 'Waiter', 'Cashier']));
    }

    public function getBranchAdminUserIds($branchIds): \Illuminate\Support\Collection
    {
        $ids = is_array($branchIds) || $branchIds instanceof \Illuminate\Support\Collection
            ? collect($branchIds)
            : collect([$branchIds]);

        $validBranchIds = $ids->filter()->unique()->values()->all();
        if (empty($validBranchIds)) {
            return collect();
        }

        $directUserIds = BranchAdmin::whereIn('branch_id', $validBranchIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $byEmailUserIds = BranchAdmin::whereIn('branch_id', $validBranchIds)
            ->join('users', 'branch_admins.email', '=', 'users.email')
            ->pluck('users.id');

        return $directUserIds->merge($byEmailUserIds)->unique()->values();
    }

    public function getUserBranchId(User $user): ?int
    {
        return $user->branch_id
            ?? BranchAdmin::where('user_id', $user->id)->orWhere('email', $user->email)->value('branch_id')
            ?? Staff::where('email', $user->email)->value('branch_id')
            ?? $user->driver?->branch_id
            ?? Driver::where('user_id', $user->id)->value('branch_id');
    }
}

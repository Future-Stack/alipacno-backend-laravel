<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed System Permissions
        $permissions = [
            ['name' => 'manage_users', 'module' => 'User Management'],
            ['name' => 'manage_roles', 'module' => 'User Management'],
            ['name' => 'manage_branches', 'module' => 'Restaurant Management'],
            ['name' => 'manage_menu', 'module' => 'Menu Catalog'],
            ['name' => 'manage_orders', 'module' => 'Order Lifecycle'],
            ['name' => 'process_pos', 'module' => 'POS System'],
            ['name' => 'view_kds', 'module' => 'Kitchen Operations'],
            ['name' => 'manage_inventory', 'module' => 'Inventory Control'],
            ['name' => 'manage_deliveries', 'module' => 'Delivery Management'],
            ['name' => 'manage_crm', 'module' => 'CRM & Call Center'],
            ['name' => 'manage_marketing', 'module' => 'Marketing Automation'],
            ['name' => 'manage_digital_signage', 'module' => 'Digital Signage'],
            ['name' => 'view_reports', 'module' => 'Reports & Analytics'],
            ['name' => 'manage_settings', 'module' => 'System Settings'],
        ];

        $permissionModels = [];
        foreach ($permissions as $p) {
            $permissionModels[$p['name']] = Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        // 2. Seed System Roles
        $roles = [
            [
                'name' => 'Super Admin',
                'description' => 'Full enterprise system control across all modules and branches.',
                'permissions' => array_keys($permissionModels)
            ],
            [
                'name' => 'HQ Admin',
                'description' => 'Headquarters administrative access.',
                'permissions' => ['manage_branches', 'manage_menu', 'manage_inventory', 'view_reports', 'manage_crm', 'manage_marketing', 'manage_digital_signage']
            ],
            [
                'name' => 'Branch Manager',
                'description' => 'Branch operational manager.',
                'permissions' => ['manage_orders', 'process_pos', 'view_kds', 'manage_inventory', 'manage_deliveries', 'view_reports']
            ],
            [
                'name' => 'Cashier',
                'description' => 'Front counter POS cashier.',
                'permissions' => ['process_pos', 'manage_orders']
            ],
            [
                'name' => 'Chef',
                'description' => 'Kitchen Display System station chef.',
                'permissions' => ['view_kds', 'manage_orders']
            ],
            [
                'name' => 'Waiter',
                'description' => 'Dine-in floor waiter.',
                'permissions' => ['manage_orders', 'process_pos']
            ],
            [
                'name' => 'Delivery Driver',
                'description' => 'Fleet delivery driver.',
                'permissions' => ['manage_deliveries']
            ],
            [
                'name' => 'Customer',
                'description' => 'Standard mobile / online food ordering customer.',
                'permissions' => []
            ],
        ];

        $roleModels = [];

        foreach ($roles as $r) {
            $role = Role::firstOrCreate(['name' => $r['name']], ['description' => $r['description']]);
            $roleModels[$r['name']] = $role;

            $permIds = [];
            foreach ($r['permissions'] as $pName) {
                if (isset($permissionModels[$pName])) {
                    $permIds[] = $permissionModels[$pName]->id;
                }
            }
            $role->permissions()->sync($permIds);
        }

        // 3. Seed Accounts for ALL Roles
        $usersToSeed = [
            [
                'name' => 'Super Administrator',
                'email' => 'admin@restaurant.com',
                'phone' => '+447000000001',
                'password' => Hash::make('password123'),
                'user_type' => 'super_admin',
                'role_id' => $roleModels['Super Admin']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'HQ Administrator',
                'email' => 'hqadmin@restaurant.com',
                'phone' => '+447000000002',
                'password' => Hash::make('password123'),
                'user_type' => 'hq_admin',
                'role_id' => $roleModels['HQ Admin']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'Branch Manager',
                'email' => 'manager@restaurant.com',
                'phone' => '+447000000003',
                'password' => Hash::make('password123'),
                'user_type' => 'branch_admin',
                'role_id' => $roleModels['Branch Manager']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'POS Cashier',
                'email' => 'cashier@restaurant.com',
                'phone' => '+447000000004',
                'password' => Hash::make('password123'),
                'user_type' => 'staff',
                'role_id' => $roleModels['Cashier']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'KDS Head Chef',
                'email' => 'chef@restaurant.com',
                'phone' => '+447000000005',
                'password' => Hash::make('password123'),
                'user_type' => 'staff',
                'role_id' => $roleModels['Chef']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'Dine-In Waiter',
                'email' => 'waiter@restaurant.com',
                'phone' => '+447000000006',
                'password' => Hash::make('password123'),
                'user_type' => 'staff',
                'role_id' => $roleModels['Waiter']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'Delivery Driver',
                'email' => 'driver@restaurant.com',
                'phone' => '+447000000007',
                'password' => Hash::make('password123'),
                'user_type' => 'staff',
                'role_id' => $roleModels['Delivery Driver']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'Demo Customer',
                'email' => 'customer@restaurant.com',
                'phone' => '+447000000008',
                'password' => Hash::make('password123'),
                'user_type' => 'customer',
                'role_id' => $roleModels['Customer']->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
        ];

        foreach ($usersToSeed as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}

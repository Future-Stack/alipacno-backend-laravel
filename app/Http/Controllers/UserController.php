<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\UserAddress;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /**
     * Display a listing of users with search, filters, pagination, and KPI statistics.
     */
    public function index(Request $request)
    {
        $query = User::with(['role', 'addresses']);

        // By default, always exclude Super Admin from the user list
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        } else {
            $query->where('user_type', '!=', 'super_admin')
                  ->where(function ($q) {
                      $q->whereNull('role_id')
                        ->orWhereHas('role', function ($rq) {
                            $rq->where('name', '!=', 'Super Admin');
                        });
                  });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->latest();

        // If requested all records
        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->get()
            ]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        // Calculate KPI Summary (Excluding Super Admin)
        $totalUsers = User::where('user_type', '!=', 'super_admin')->count();
        $activeUsers = User::where('user_type', '!=', 'super_admin')->where('status', 'active')->count();
        $inactiveUsers = User::where('user_type', '!=', 'super_admin')->where('status', 'inactive')->count();
        $blockedUsers = User::where('user_type', '!=', 'super_admin')->where('status', 'blocked')->count();
        $customersCount = User::where('user_type', 'customer')->count();
        $staffCount = User::whereIn('user_type', ['staff', 'branch_admin', 'cashier', 'kitchen', 'driver'])->count();
        $adminCount = User::whereIn('user_type', ['super_admin', 'admin', 'hq_admin'])->count();

        return response()->json([
            'success' => true,
            'kpis' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'blocked_users' => $blockedUsers,
                'customers' => $customersCount,
                'staff' => $staffCount,
                'admins' => $adminCount,
            ],
            'users' => $paginated,
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|unique:users,phone',
            'gender' => 'nullable|string|in:male,female,other',
            'avatar' => 'nullable', // Accepts image file or string path
            'user_image' => 'nullable', // Accepts image file or string path
            'user_type' => 'nullable|string|max:50',
            'role_id' => 'nullable|exists:roles,id',
            'role' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked',
            'loyalty_points_balance' => 'nullable|integer|min:0',
        ]);

        $password = !empty($validated['password']) ? $validated['password'] : 'password123';
        $validated['password'] = Hash::make($password);
        $validated['user_type'] = $validated['user_type'] ?? 'customer';
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['email_verified_at'] = now();
        $validated['terms_accepted'] = true;

        // Auto-resolve role_id by role name if provided
        if (empty($validated['role_id']) && !empty($validated['role'])) {
            $matchedRole = Role::where('name', 'like', $validated['role'])->first();
            if ($matchedRole) {
                $validated['role_id'] = $matchedRole->id;
            }
        }

        // Handle avatar file upload
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        // Handle user_image file upload
        if ($request->hasFile('user_image')) {
            $request->validate(['user_image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            $validated['user_image'] = $request->file('user_image')->store('users/images', 'public');
        }

        unset($validated['role']);
        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load(['role', 'addresses']),
        ], 201);
    }

    /**
     * Display the specified user details.
     */
    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => $user->load(['role.permissions', 'addresses', 'driver', 'branchAdmin']),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|unique:users,phone,' . $user->id,
            'password' => 'nullable|string|min:6',
            'gender' => 'nullable|string|in:male,female,other',
            'avatar' => 'nullable',
            'user_image' => 'nullable',
            'user_type' => 'sometimes|string|max:50',
            'role_id' => 'nullable|exists:roles,id',
            'role' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,blocked',
            'loyalty_points_balance' => 'nullable|integer|min:0',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Auto-resolve role_id by role name if provided
        if (empty($validated['role_id']) && !empty($validated['role'])) {
            $matchedRole = Role::where('name', 'like', $validated['role'])->first();
            if ($matchedRole) {
                $validated['role_id'] = $matchedRole->id;
            }
        }

        // Handle avatar image update & old file deletion
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        // Handle user_image update & old file deletion
        if ($request->hasFile('user_image')) {
            $request->validate(['user_image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            if ($user->user_image && Storage::disk('public')->exists($user->user_image)) {
                Storage::disk('public')->delete($user->user_image);
            }
            $validated['user_image'] = $request->file('user_image')->store('users/images', 'public');
        }

        unset($validated['role']);
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->load(['role', 'addresses']),
        ]);
    }

    /**
     * Quick status update for a user (active, inactive, blocked).
     */
    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,blocked',
        ]);

        $user->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => "User status updated to {$validated['status']} successfully.",
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified user from storage (Permanent Hard Delete).
     */
    public function destroy(User $user)
    {
        // Delete stored image files from disk if exist
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        if ($user->user_image && Storage::disk('public')->exists($user->user_image)) {
            Storage::disk('public')->delete($user->user_image);
        }

        // Permanent Hard Delete
        $user->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'User permanently deleted successfully.',
        ], 200);
    }

    /**
     * Bulk Import Users from CSV File.
     */
    /**
     * Bulk Import Users from CSV File.
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
            'update_existing' => 'nullable|boolean', // if true, updates existing emails instead of skipping
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to read the uploaded CSV file.',
            ], 422);
        }

        // Read header row
        $headers = fgetcsv($handle, 2000, ',');
        if (!$headers) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'CSV file is empty.',
            ], 422);
        }

        // Normalize header keys (lowercase, trim, remove non-alphanumeric)
        $normalizedHeaders = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace([' ', '-'], '_', $h))));
        }, $headers);

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];
        $rowNumber = 1;

        // Default update_existing to true so every CSV row is either created or updated in DB without being dropped/skipped
        $updateExisting = $request->has('update_existing') ? $request->boolean('update_existing') : true;
        $rolesCache = Role::pluck('id', 'name')->toArray();

        // 1. Pre-hash default password once for ultra-fast performance
        $defaultPasswordHash = Hash::make('password123');

        // 2. In-memory cache for existing users to eliminate thousands of slow DB roundtrips
        $existingEmails = User::withTrashed()->pluck('id', 'email')->toArray();
        $existingPhones = User::withTrashed()->whereNotNull('phone')->where('phone', '!=', '')->pluck('id', 'phone')->toArray();

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $rowNumber++;
                if (count(array_filter($row)) === 0) {
                    continue; // Skip empty rows
                }

                $rowData = [];
                foreach ($normalizedHeaders as $index => $headerKey) {
                    $rowData[$headerKey] = isset($row[$index]) ? trim($row[$index]) : null;
                }

                // 1. Resolve Customer Name
                $name = $rowData['name'] 
                    ?? $rowData['customer'] 
                    ?? $rowData['customer_name'] 
                    ?? $rowData['full_name'] 
                    ?? $rowData['user_name'] 
                    ?? null;
                $name = !empty(trim((string) $name)) ? trim((string) $name) : null;

                // 2. Resolve Telephone / Phone
                $phone = $rowData['phone'] 
                    ?? $rowData['telephone'] 
                    ?? $rowData['mobile'] 
                    ?? $rowData['phone_number'] 
                    ?? $rowData['contact'] 
                    ?? null;

                if (!empty($phone)) {
                    $phoneStr = trim((string) $phone);
                    // Convert scientific notation (e.g. 7.73E+09) to normal numeric string
                    if (stripos($phoneStr, 'e+') !== false || stripos($phoneStr, 'e-') !== false) {
                        $phoneStr = sprintf('%.0f', (float) $phoneStr);
                    }
                    $phone = !empty($phoneStr) ? $phoneStr : null;
                } else {
                    $phone = null;
                }

                // If name is still empty, fallback using phone or row
                if (empty($name)) {
                    $name = $phone ? "Customer " . substr($phone, -4) : "Customer #{$rowNumber}";
                }

                // 3. Resolve E-Mail & Fallback Generator
                $email = $rowData['email'] 
                    ?? $rowData['e_mail'] 
                    ?? $rowData['email_address'] 
                    ?? null;
                $email = !empty(trim((string) $email)) ? strtolower(trim((string) $email)) : null;

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $cleanPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
                    $baseSlug = $cleanPhone ?: Str::slug($name ?: 'user', '_');
                    $email = "customer_{$baseSlug}@guest.local";

                    // Ensure generated fallback email is unique via fast in-memory cache
                    $attempts = 0;
                    while (isset($existingEmails[$email]) && $attempts < 10) {
                        $email = "customer_{$baseSlug}_" . substr(md5(uniqid((string) mt_rand(), true)), 0, 5) . "@guest.local";
                        $attempts++;
                    }
                }

                // 4. Resolve Address Info
                $address = $rowData['address'] 
                    ?? $rowData['address_line_1'] 
                    ?? $rowData['street_address'] 
                    ?? $rowData['street'] 
                    ?? null;
                $address = !empty(trim((string) $address)) ? trim((string) $address) : null;

                $city = $rowData['city'] ?? $rowData['town'] ?? null;
                $city = !empty(trim((string) $city)) ? trim((string) $city) : null;

                $postcode = $rowData['postcode'] ?? $rowData['postal_code'] ?? $rowData['zip'] ?? $rowData['zipcode'] ?? $rowData['zip_code'] ?? null;
                $postcode = !empty(trim((string) $postcode)) ? trim((string) $postcode) : null;

                // 5. Resolve Other Meta
                $gender = strtolower($rowData['gender'] ?? $rowData['sex'] ?? 'male');
                if (!in_array($gender, ['male', 'female', 'other'])) {
                    $gender = 'male';
                }

                // Strictly import as customer and protect administrative roles
                $userType = 'customer';
                $roleName = $rowData['role'] ?? $rowData['role_name'] ?? null;
                $status = strtolower($rowData['status'] ?? 'active');
                if (!in_array($status, ['active', 'inactive', 'blocked'])) {
                    $status = 'active';
                }
                
                $passwordHash = !empty($rowData['password']) && $rowData['password'] !== 'password123'
                    ? Hash::make($rowData['password'])
                    : $defaultPasswordHash;

                // 6. Resolve Role ID
                $roleId = null;
                if ($roleName) {
                    foreach ($rolesCache as $rName => $rId) {
                        if (strcasecmp($rName, $roleName) === 0) {
                            $roleId = $rId;
                            break;
                        }
                    }
                }

                try {
                    // Match existing user: First prioritize phone (most accurate for customers), then email
                    $existingUserId = null;
                    if (!empty($phone) && isset($existingPhones[$phone])) {
                        $existingUserId = $existingPhones[$phone];
                    } elseif (!empty($email) && isset($existingEmails[$email])) {
                        $existingUserId = $existingEmails[$email];
                    }

                    if ($existingUserId) {
                        if ($updateExisting) {
                            $existingUser = User::withTrashed()->find($existingUserId);
                            
                            // Safety: Never overwrite super_admin with CSV customer import
                            if ($existingUser && $existingUser->user_type === 'super_admin') {
                                continue;
                            }

                            if ($existingUser) {
                                $updateData = [
                                    'name' => $name ?: $existingUser->name,
                                    'gender' => $gender ?: $existingUser->gender,
                                    'user_type' => 'customer',
                                    'status' => $status ?: $existingUser->status,
                                ];
                                if ($roleId) {
                                    $updateData['role_id'] = $roleId;
                                }

                                // Only update phone if no other user has this phone
                                if (!empty($phone)) {
                                    if (!isset($existingPhones[$phone]) || $existingPhones[$phone] == $existingUser->id) {
                                        $updateData['phone'] = $phone;
                                        $existingPhones[$phone] = $existingUser->id;
                                    }
                                }

                                // Only update email if no other user has this email
                                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    if (!isset($existingEmails[$email]) || $existingEmails[$email] == $existingUser->id) {
                                        $updateData['email'] = $email;
                                        $existingEmails[$email] = $existingUser->id;
                                    }
                                }

                                $existingUser->update($updateData);

                                if ($existingUser->trashed()) {
                                    $existingUser->restore();
                                }

                                // Save/Update Address if present
                                if ($address || $city || $postcode) {
                                    UserAddress::updateOrCreate(
                                        ['user_id' => $existingUser->id],
                                        [
                                            'contact_name' => $existingUser->name,
                                            'phone' => $existingUser->phone,
                                            'address_line_1' => $address,
                                            'address' => $address,
                                            'city' => $city,
                                            'postcode' => $postcode,
                                            'label' => 'Home',
                                            'is_default' => true,
                                        ]
                                    );
                                }

                                $updatedCount++;
                            }
                        } else {
                            $skippedCount++;
                            $errors[] = "Row #{$rowNumber}: User {$email} / {$phone} already exists (skipped).";
                        }
                    } else {
                        // Ensure unique email for new user
                        $attempts = 0;
                        while (isset($existingEmails[$email]) && $attempts < 20) {
                            $email = "customer_" . ($phone ? preg_replace('/[^0-9]/', '', $phone) : Str::slug($name ?: 'user', '_')) . "_" . substr(md5(uniqid((string) mt_rand(), true)), 0, 5) . "@guest.local";
                            $attempts++;
                        }

                        // Ensure phone is not conflicting
                        $phoneToSave = (!empty($phone) && !isset($existingPhones[$phone])) ? $phone : null;

                        $newUser = User::create([
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phoneToSave,
                            'gender' => $gender,
                            'password' => $passwordHash,
                            'user_type' => 'customer',
                            'role_id' => $roleId,
                            'status' => $status,
                            'email_verified_at' => now(),
                            'terms_accepted' => true,
                        ]);

                        // Register in in-memory cache
                        $existingEmails[$email] = $newUser->id;
                        if ($phoneToSave) {
                            $existingPhones[$phoneToSave] = $newUser->id;
                        }

                        // Save Address if present
                        if ($address || $city || $postcode) {
                            UserAddress::create([
                                'user_id' => $newUser->id,
                                'contact_name' => $newUser->name,
                                'phone' => $newUser->phone,
                                'address_line_1' => $address,
                                'address' => $address,
                                'city' => $city,
                                'postcode' => $postcode,
                                'label' => 'Home',
                                'is_default' => true,
                            ]);
                        }

                        $importedCount++;
                    }
                } catch (\Exception $rowEx) {
                    $skippedCount++;
                    $errors[] = "Row #{$rowNumber}: " . $rowEx->getMessage();
                }
            }

            DB::commit();
            fclose($handle);

            return response()->json([
                'success' => true,
                'message' => "CSV Import processed successfully. {$importedCount} created, {$updatedCount} updated, {$skippedCount} skipped.",
                'summary' => [
                    'imported_count' => $importedCount,
                    'updated_count' => $updatedCount,
                    'skipped_count' => $skippedCount,
                    'total_processed' => $importedCount + $updatedCount + $skippedCount,
                ],
                'errors' => array_slice($errors, 0, 50),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            return response()->json([
                'success' => false,
                'message' => 'Error importing CSV: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download Sample CSV Template for Bulk User Import.
     */
    public function sampleCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers-sample-template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            
            // CSV Header Row
            fputcsv($handle, ['Customer', 'Telephone', 'E-Mail', 'Address', 'City', 'Postcode', 'Gender', 'Status']);

            // Sample Example Rows
            fputcsv($handle, ['John Doe', '+13125550101', 'john.doe@example.com', '1 Witting Close', 'London', 'CO16 8UZ', 'male', 'active']);
            fputcsv($handle, ['Sarah Jenkins', '+13125550102', 'sarah.j@example.com', '14 Main St', 'Manchester', 'M1 4BT', 'female', 'active']);
            fputcsv($handle, ['Michael Brown', '+13125550103', 'michael.b@example.com', '22 High Street', 'Birmingham', 'B1 1AA', 'male', 'active']);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Users list to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = User::with(['role', 'addresses']);

        // Strictly exclude Super Admin from export
        $query->where('user_type', '!=', 'super_admin')
              ->where(function ($q) {
                  $q->whereNull('role_id')
                    ->orWhereHas('role', function ($rq) {
                        $rq->where('name', '!=', 'Super Admin');
                    });
              });

        // Filter by role_id if provided
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Filter by user_type if provided, otherwise default to customers only (unless role_id is specified)
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        } elseif (!$request->filled('role_id')) {
            $query->where('user_type', 'customer');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-export-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Customer', 'Telephone', 'E-Mail', 'Address', 'City', 'Postcode', 'Gender', 'User Type', 'Role', 'Status', 'Created At']);

            $query->orderBy('id')->chunk(500, function ($users) use ($handle) {
                foreach ($users as $u) {
                    $defaultAddr = $u->addresses->where('is_default', true)->first() ?? $u->addresses->first();
                    fputcsv($handle, [
                        $u->id,
                        $u->name,
                        $u->phone,
                        $u->email,
                        $defaultAddr?->address_line_1 ?? $defaultAddr?->address ?? '',
                        $defaultAddr?->city ?? '',
                        $defaultAddr?->postcode ?? '',
                        $u->gender,
                        $u->user_type,
                        $u->role?->name ?? $u->user_type,
                        $u->status,
                        $u->created_at ? $u->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get Helper Options (Roles, User Types, Statuses) for frontend dropdowns.
     */
    public function helperOptions()
    {
        $roles = Role::select('id', 'name', 'description')->get();
        $userTypes = [
            ['value' => 'customer', 'label' => 'Customer'],
            ['value' => 'staff', 'label' => 'Staff'],
            ['value' => 'cashier', 'label' => 'Cashier'],
            ['value' => 'branch_admin', 'label' => 'Branch Admin / Manager'],
            ['value' => 'hq_admin', 'label' => 'HQ Admin'],
            ['value' => 'super_admin', 'label' => 'Super Admin'],
            ['value' => 'driver', 'label' => 'Driver / Rider'],
        ];

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'user_types' => $userTypes,
            'statuses' => ['active', 'inactive', 'blocked'],
            'genders' => ['male', 'female', 'other'],
        ]);
    }
}



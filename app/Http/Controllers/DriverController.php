<?php

namespace App\Http\Controllers;

use App\Mail\DriverKycStatusMail;
use App\Models\Driver;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DriverController extends Controller
{
    /**
     * Display a listing of drivers.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = Driver::with(['branch', 'user', 'deliveries' => function ($q) {
            $q->whereIn('delivery_status', ['assigned', 'picked_up', 'on_the_way']);
        }]);

        // Security / Scope check: regular drivers can ONLY see their own profile
        if (!$authUser->isSuperAdmin() && !$authUser->isBranchAdmin()) {
            $query->where('user_id', $authUser->id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('kyc_status')) {
            $query->where('kyc_status', $request->kyc_status);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('vehicle_type', 'like', "%{$search}%")
                  ->orWhere('license_number', 'like', "%{$search}%");
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created driver or update existing by user_id / phone.
     */
    public function store(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'vehicle_type' => 'nullable|string|max:100',
            'license_number' => 'required|string|max:100',
            'license_image' => 'nullable',
            'kyc_status' => 'nullable|in:pending,submitted,approved,rejected',
            'reject_reason' => 'nullable|string|max:1000',
            'is_online' => 'nullable|boolean',
            'status' => 'nullable|in:available,on_delivery,offline',
        ]);

        // Security check: non-admin users can ONLY create/submit their own driver profile
        if (!$authUser->isSuperAdmin() && !$authUser->isBranchAdmin()) {
            if (!empty($validated['user_id']) && (int) $validated['user_id'] !== (int) $authUser->id) {
                return response()->json([
                    'message' => 'Unauthorized: You cannot create or submit a driver profile for another user ID.',
                    'authenticated_user_id' => $authUser->id,
                    'provided_user_id' => (int) $validated['user_id'],
                ], 403);
            }
            $validated['user_id'] = $authUser->id;
        } else {
            $validated['user_id'] = $validated['user_id'] ?? $authUser->id;
        }

        // Handle optional license file/image upload (supports JPEG, PNG, JPG, WEBP, PDF)
        if ($request->hasFile('license_image')) {
            $request->validate(['license_image' => 'file|mimes:jpeg,png,jpg,webp,pdf|max:10240']);
            $validated['license_image'] = $request->file('license_image')->store('drivers/licenses', 'public');
        }

        $validated['vehicle_type'] = $validated['vehicle_type'] ?? 'Motorcycle';
        $validated['kyc_status'] = $validated['kyc_status'] ?? 'submitted';
        $validated['is_online'] = $validated['is_online'] ?? false;
        $validated['status'] = $validated['status'] ?? 'available';
        $validated['reject_reason'] = null;

        // Match existing driver by user_id if present, otherwise by phone
        $matchCriteria = !empty($validated['user_id'])
            ? ['user_id' => $validated['user_id']]
            : ['phone' => $validated['phone']];

        $driver = Driver::updateOrCreate($matchCriteria, $validated);
        $freshDriver = $driver->load(['branch', 'user']);

        // In-app Bell Notification for Branch Managers and Super Admins
        try {
            Notification::create([
                'user_id' => null, // Available for Super Admins and Branch Managers
                'branch_id' => $freshDriver->branch_id,
                'title' => 'Driver KYC Submitted for Verification',
                'message' => "Driver '{$freshDriver->name}' has submitted KYC documents for verification and approval.",
                'type' => 'delivery',
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Driver KYC Submission Admin Notification Error: ' . $e->getMessage());
        }

        return response()->json($freshDriver, 200);
    }

    /**
     * Display the specified driver.
     */
    public function show(Request $request, Driver $driver)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Security check: regular drivers can only view their own driver profile
        if (!$authUser->isSuperAdmin() && !$authUser->isBranchAdmin()) {
            if ((int) $driver->user_id !== (int) $authUser->id) {
                return response()->json([
                    'message' => 'Unauthorized: You can only view your own driver profile.'
                ], 403);
            }
        }

        return response()->json($driver->load(['branch', 'user', 'deliveries.order']));
    }

    /**
     * Update the specified driver.
     */
    public function update(Request $request, Driver $driver)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'vehicle_type' => 'sometimes|string|max:100',
            'license_number' => 'sometimes|string|max:100',
            'license_image' => 'nullable',
            'kyc_status' => 'sometimes|in:pending,submitted,approved,rejected',
            'reject_reason' => 'nullable|string|max:1000',
            'is_online' => 'sometimes|boolean',
            'status' => 'sometimes|in:available,on_delivery,offline',
        ]);

        // Security check: non-admin users can ONLY update their own driver profile
        if (!$authUser->isSuperAdmin() && !$authUser->isBranchAdmin()) {
            if ((int) $driver->user_id !== (int) $authUser->id) {
                return response()->json([
                    'message' => 'Unauthorized: You can only update your own driver profile.'
                ], 403);
            }
            unset($validated['user_id']); // Cannot transfer driver to another user
        }

        // Handle optional license file/image upload (supports JPEG, PNG, JPG, WEBP, PDF)
        if ($request->hasFile('license_image')) {
            $request->validate(['license_image' => 'file|mimes:jpeg,png,jpg,webp,pdf|max:10240']);
            $validated['license_image'] = $request->file('license_image')->store('drivers/licenses', 'public');
        }

        $isResubmitting = in_array($driver->kyc_status, ['pending', 'rejected']);

        // If driver details are updated and kyc_status is not explicitly passed, auto-submit
        if (!isset($validated['kyc_status']) && $isResubmitting) {
            $validated['kyc_status'] = 'submitted';
            $validated['reject_reason'] = null;
        }

        $driver->update($validated);
        $freshDriver = $driver->load(['branch', 'user']);

        // If driver resubmitted info, notify Super Admin and Branch Manager
        if ($isResubmitting && ($freshDriver->kyc_status === 'submitted')) {
            try {
                Notification::create([
                    'user_id' => null,
                    'branch_id' => $freshDriver->branch_id,
                    'title' => 'Driver KYC Resubmitted for Approval',
                    'message' => "Driver '{$freshDriver->name}' has updated and resubmitted KYC details for verification.",
                    'type' => 'delivery',
                    'is_read' => false,
                ]);
            } catch (\Exception $e) {
                Log::error('Driver KYC Resubmission Notification Error: ' . $e->getMessage());
            }
        }

        return response()->json($freshDriver);
    }

    /**
     * Remove the specified driver.
     */
    public function destroy(Request $request, Driver $driver)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Security check: only super admin or branch admin can delete drivers
        if (!$authUser->isSuperAdmin() && !$authUser->isBranchAdmin()) {
            return response()->json([
                'message' => 'Unauthorized: Only admins can delete driver accounts.'
            ], 403);
        }

        $driver->delete();

        return response()->json(null, 204);
    }

    /**
     * Update driver availability status.
     */
    public function updateStatus(Request $request, Driver $driver)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'is_online' => 'nullable|boolean',
            'status' => 'nullable|in:available,on_delivery,offline',
        ]);

        $driver->update($validated);

        return response()->json([
            'message' => 'Driver status updated successfully.',
            'driver' => $driver->fresh(['branch', 'user']),
        ]);
    }

    /**
     * Submit driver KYC documents / info (Driver).
     * Auto sets kyc_status to 'submitted'.
     */
    public function submitKyc(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $authUser;
        $driver = $user->driver;

        $validated = $request->validate([
            'vehicle_type' => 'nullable|string|max:100',
            'license_number' => 'required|string|max:100',
            'license_image' => 'nullable',
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        // Handle optional license file/image upload (supports JPEG, PNG, JPG, WEBP, PDF)
        if ($request->hasFile('license_image')) {
            $request->validate(['license_image' => 'file|mimes:jpeg,png,jpg,webp,pdf|max:10240']);
            $validated['license_image'] = $request->file('license_image')->store('drivers/licenses', 'public');
        }

        if (!$driver) {
            $driver = Driver::create([
                'user_id' => $user->id,
                'branch_id' => $validated['branch_id'] ?? 1,
                'name' => $validated['name'] ?? $user->name,
                'phone' => $validated['phone'] ?? ($user->phone ?? 'N/A'),
                'vehicle_type' => $validated['vehicle_type'] ?? 'Motorcycle',
                'license_number' => $validated['license_number'],
                'license_image' => $validated['license_image'] ?? null,
                'kyc_status' => 'submitted',
                'is_online' => false,
                'status' => 'available',
                'reject_reason' => null,
            ]);
        } else {
            $updateData = array_filter($validated, fn($val) => !is_null($val));
            $updateData['kyc_status'] = 'submitted';
            $updateData['reject_reason'] = null; // Clear previous rejection reason upon resubmitting

            $driver->update($updateData);
        }

        $freshDriver = $driver->fresh(['branch', 'user']);

        // In-app Bell Notification for Branch / Admin
        try {
            Notification::create([
                'user_id' => null, // broadcast to branch / all admins
                'branch_id' => $freshDriver->branch_id,
                'title' => 'New Driver KYC Submitted',
                'message' => "Driver '{$freshDriver->name}' has submitted KYC documents for verification.",
                'type' => 'delivery',
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('KYC Submission Notification Error: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'KYC documents and information submitted successfully. Waiting for admin approval.',
            'kyc_status' => 'submitted',
            'is_online' => (bool) $freshDriver->is_online,
            'status' => $freshDriver->status,
            'driver' => $freshDriver,
        ]);
    }

    /**
     * Update driver KYC approval status (Super Admin / Branch Manager).
     * Automatically handles approval / rejection, sends email, and creates in-app notification.
     */
    public function updateKycStatus(Request $request, Driver $driver)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Security check: only super admin or branch admin can change KYC status
        if (!$authUser->isSuperAdmin() && !$authUser->isBranchAdmin()) {
            return response()->json([
                'message' => 'Unauthorized: Only admins or branch managers can update KYC status.'
            ], 403);
        }

        $validated = $request->validate([
            'kyc_status' => 'required|in:pending,submitted,approved,rejected',
            'reject_reason' => 'required_if:kyc_status,rejected|nullable|string|max:1000',
            'reason' => 'nullable|string|max:1000', // alias for reject_reason
        ]);

        $status = $validated['kyc_status'];
        $rejectReason = $validated['reject_reason'] ?? $request->input('reason');

        $updateData = [
            'kyc_status' => $status,
        ];

        if ($status === 'approved') {
            $updateData['is_online'] = true;
            $updateData['status'] = 'available';
            $updateData['reject_reason'] = null;
        } elseif ($status === 'rejected') {
            $updateData['is_online'] = false;
            $updateData['status'] = 'offline';
            $updateData['reject_reason'] = $rejectReason;
        } elseif ($status === 'pending') {
            $updateData['is_online'] = false;
            $updateData['status'] = 'available';
        }

        $driver->update($updateData);
        $freshDriver = $driver->fresh(['branch', 'user']);

        // 1. Send Email to the driver
        $driverEmail = $freshDriver->user?->email;
        if ($driverEmail) {
            try {
                if ($status === 'rejected') {
                    Mail::to($driverEmail)->send(new DriverKycStatusMail($freshDriver, 'rejected', $rejectReason));
                } elseif ($status === 'approved') {
                    Mail::to($driverEmail)->send(new DriverKycStatusMail($freshDriver, 'approved'));
                }
            } catch (\Exception $e) {
                Log::error("Driver KYC Email Error for {$driverEmail}: " . $e->getMessage());
            }
        }

        // 2. Create In-App Bell Notification for the Driver
        $targetUserId = $freshDriver->user_id ?? \App\Models\User::where('phone', $freshDriver->phone)->value('id');
        if ($targetUserId) {
            try {
                $notificationTitle = $status === 'approved'
                    ? 'KYC Verification Approved'
                    : ($status === 'rejected'
                        ? 'KYC Verification Rejected'
                        : 'KYC Status Updated');

                $notificationMessage = $status === 'approved'
                    ? 'Congratulations! Your Driver KYC documents have been approved. You can now go online and accept delivery orders.'
                    : ($status === 'rejected'
                        ? "Your Driver KYC verification was rejected. Reason: {$rejectReason}"
                        : "Your Driver KYC status is now: " . ucfirst($status));

                Notification::create([
                    'user_id' => $targetUserId,
                    'branch_id' => $freshDriver->branch_id,
                    'title' => $notificationTitle,
                    'message' => $notificationMessage,
                    'type' => 'delivery',
                    'is_read' => false,
                ]);
            } catch (\Exception $e) {
                Log::error('Driver KYC In-App Notification Error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => "Driver KYC status updated to '{$status}' successfully." . ($status === 'rejected' ? ' Rejection reason has been emailed and notified to the driver.' : ''),
            'kyc_status' => $freshDriver->kyc_status,
            'is_online' => (bool) $freshDriver->is_online,
            'status' => $freshDriver->status,
            'reject_reason' => $freshDriver->reject_reason,
            'driver' => $freshDriver,
        ]);
    }
}
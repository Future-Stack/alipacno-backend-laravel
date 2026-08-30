<?php

namespace App\Http\Controllers;

use App\Events\OrderAcceptedBroadcastEvent;
use App\Mail\DriverKycStatusMail;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverDeclinedOrder;
use App\Models\Notification;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            if ($request->status === 'available') {
                $query->whereDoesntHave('deliveries', function ($q) {
                    $q->whereIn('delivery_status', ['assigned', 'picked_up', 'on_the_way']);
                });
            }
        }

        if ($request->has('is_online')) {
            $query->where('is_online', $request->boolean('is_online'));
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
            'branch_id' => 'nullable|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
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

        // Resolve name, phone, and branch fallbacks from user and database
        $targetUser = ($validated['user_id'] == $authUser->id) ? $authUser : \App\Models\User::find($validated['user_id']);
        $validated['name'] = !empty($validated['name']) ? $validated['name'] : ($targetUser?->name ?? 'Driver');
        $validated['phone'] = !empty($validated['phone']) ? $validated['phone'] : ($targetUser?->phone ?? '');
        $validated['branch_id'] = $validated['branch_id'] ?? \App\Models\Branch::value('id') ?? 1;

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

        $matchCriteria = ['user_id' => $user->id];

        $driverData = [
            'branch_id' => $validated['branch_id'] ?? ($driver?->branch_id ?? (\App\Models\Branch::value('id') ?? 1)),
            'name' => $validated['name'] ?? ($driver?->name ?? ($user->name ?? 'Driver')),
            'phone' => $validated['phone'] ?? ($driver?->phone ?? ($user->phone ?? '')),
            'vehicle_type' => $validated['vehicle_type'] ?? ($driver?->vehicle_type ?? 'Motorcycle'),
            'license_number' => $validated['license_number'],
            'kyc_status' => 'submitted',
            'reject_reason' => null,
            'is_online' => $driver?->is_online ?? false,
            'status' => $driver?->status ?? 'available',
        ];

        if (isset($validated['license_image'])) {
            $driverData['license_image'] = $validated['license_image'];
        }

        $driver = Driver::updateOrCreate($matchCriteria, $driverData);

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

    /**
     * Get Upcoming Unassigned Delivery Requests for the authenticated driver's branch.
     * Matches the mobile app's 'Upcoming Request' card view.
     */
    public function upcomingRequests(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $driver = $authUser->driver;
        if (!$driver) {
            // If user has role admin, allow passing driver_id or return empty
            if ($request->filled('driver_id') && ($authUser->isSuperAdmin() || $authUser->isBranchAdmin())) {
                $driver = Driver::find($request->driver_id);
            }
        }

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found.'], 404);
        }

        // Check if driver is online
        if (!$driver->is_online) {
            return response()->json([
                'upcoming_count' => 0,
                'is_online' => false,
                'message' => 'Driver is currently offline. Please turn online status ON to view and receive upcoming delivery requests.',
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => (int) $request->input('per_page', 15),
                'total' => 0,
                'data' => [],
            ]);
        }

        // Get IDs of orders declined by this driver
        $declinedOrderIds = DriverDeclinedOrder::where('driver_id', $driver->id)->pluck('order_id')->toArray();

        $query = Order::with(['items.menuItem', 'branch', 'address'])
            ->where('order_type', 'delivery')
            ->whereNull('assigned_driver_id')
            ->whereIn('order_status', ['pending', 'accepted', 'preparing', 'ready'])
            ->whereNotIn('id', $declinedOrderIds);

        if ($driver->branch_id) {
            $query->where('branch_id', $driver->branch_id);
        }

        $query->latest();

        $orders = $query->paginate($request->input('per_page', 15));

        $formattedItems = $orders->getCollection()->map(function ($order) {
            $firstItem = $order->items->first();
            $menuItem = $firstItem?->menuItem;
            $itemsCount = $order->items->count();

            $earnings = (float) $order->delivery_fee;

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'category_tag' => $menuItem?->category?->name ?? 'Special',
                'title' => $menuItem ? $menuItem->name . ($itemsCount > 1 ? " + " . ($itemsCount - 1) . " more" : "") : 'Delivery Package',
                'image_url' => $menuItem?->image_url ?? null,
                'earnings' => $earnings,
                'formatted_earnings' => '£' . number_format($earnings, 2),
                'delivery_fee' => (float) $order->delivery_fee,
                'formatted_delivery_fee' => $order->delivery_fee > 0 ? '£' . number_format((float) $order->delivery_fee, 2) : 'Free',
                'rider_tip' => (float) $order->rider_tip,
                'formatted_rider_tip' => '£' . number_format((float) $order->rider_tip, 2),
                'total_order_amount' => (float) $order->total,
                'formatted_total_amount' => '£' . number_format((float) $order->total, 2),
                'pickup_location' => $order->branch ? ($order->branch->address ?? $order->branch->name) : 'Pacinos Branch',
                'delivery_location' => $order->delivery_address ?? ($order->address ? $order->address->address_line_1 . ', ' . $order->address->postcode : 'Customer Location'),
                'customer_name' => $order->customer_name ?? $order->user?->name ?? 'Valued Customer',
                'customer_phone' => $order->customer_phone ?? $order->user?->phone ?? 'N/A',
                'distance_km' => $order->calculateDistanceKm(),
                'distance_remaining' => $order->calculateDistanceKm() . ' km Remaining',
                'time_remaining_minutes' => $order->calculateRemainingMinutes(),
                'time_remaining' => $order->calculateRemainingMinutes() . ' mins Remaining',
                'delivery_time_formatted' => $order->estimated_delivery_time ? Carbon::parse($order->estimated_delivery_time)->format('l, M d, h:i A') : $order->created_at->format('l, M d, h:i A'),
                'created_at' => $order->created_at->toIso8601String(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->menuItem?->name ?? 'Menu Item',
                        'quantity' => $item->quantity,
                        'price' => (float) $item->unit_price,
                        'image_url' => $item->menuItem?->image_url,
                    ];
                }),
            ];
        });

        return response()->json([
            'upcoming_count' => $orders->total(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'data' => $formattedItems,
        ]);
    }

    /**
     * Driver Accepts an Order (Atomic Lock / Concurrency-Safe).
     * First driver to click 'Accept Task' claims the order.
     */
    public function acceptOrder(Request $request, Order $order)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $driver = $authUser->driver;
        if (!$driver) {
            if ($request->filled('driver_id') && ($authUser->isSuperAdmin() || $authUser->isBranchAdmin())) {
                $driver = Driver::find($request->driver_id);
            }
        }

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found for this account.'
            ], 404);
        }

        if ($driver->kyc_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your driver profile is not approved yet for accepting deliveries.'
            ], 403);
        }

        if (!$driver->is_online) {
            return response()->json([
                'success' => false,
                'is_online' => false,
                'message' => 'You are currently offline. Please turn online status ON first to accept delivery tasks.'
            ], 400);
        }

        // Concurrency-safe execution with pessimistic locking
        return DB::transaction(function () use ($order, $driver) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            // Check if order is already assigned
            if (!empty($lockedOrder->assigned_driver_id)) {
                return response()->json([
                    'success' => false,
                    'is_taken' => true,
                    'message' => 'Sorry! Another driver has already accepted this delivery task.'
                ], 409); // 409 Conflict
            }

            // Assign driver to order
            $nextStatus = in_array($lockedOrder->order_status, ['pending', 'accepted']) ? 'accepted' : $lockedOrder->order_status;
            $lockedOrder->update([
                'assigned_driver_id' => $driver->id,
                'order_status' => $nextStatus,
            ]);

            // Create or update delivery record
            $delivery = Delivery::updateOrCreate(
                ['order_id' => $lockedOrder->id],
                [
                    'driver_id' => $driver->id,
                    'delivery_status' => 'assigned',
                    'estimated_time' => now()->addMinutes(30),
                ]
            );

            // Update driver status
            $driver->update([
                'status' => 'on_delivery'
            ]);

            // Create in-app notification for the customer & branch
            try {
                if ($lockedOrder->user_id) {
                    Notification::create([
                        'user_id' => $lockedOrder->user_id,
                        'branch_id' => $lockedOrder->branch_id,
                        'title' => 'Driver Assigned to Your Order',
                        'message' => "Driver '{$driver->name}' has accepted your order #{$lockedOrder->order_number} and will deliver it soon.",
                        'type' => 'order',
                        'is_read' => false,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Order Accept Notification Error: ' . $e->getMessage());
            }

            // Broadcast Reverb WebSocket event to dismiss card on other drivers' screens
            try {
                broadcast(new OrderAcceptedBroadcastEvent($lockedOrder, $driver))->toOthers();
            } catch (\Exception $e) {
                Log::error('Order Accepted Broadcast Error: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Delivery task accepted successfully! Order moved to ongoing tasks.',
                'order' => $lockedOrder->fresh(['branch', 'user', 'items.menuItem']),
                'delivery' => $delivery->fresh(['driver', 'order']),
            ], 200);
        });
    }

    /**
     * Update Driver's Firebase Device (FCM) Token.
     */
    public function updateFcmToken(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $token = $validated['fcm_token'];

        // Update authenticated user's FCM token
        $authUser->update(['fcm_token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'FCM Device token registered successfully for push notifications.',
            'fcm_token' => $token,
        ]);
    }

    /**
     * Driver Declines an Order.
     * Hides the order for this specific driver while keeping it open for others.
     */
    public function declineOrder(Request $request, Order $order)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $driver = $authUser->driver;
        if (!$driver) {
            if ($request->filled('driver_id') && ($authUser->isSuperAdmin() || $authUser->isBranchAdmin())) {
                $driver = Driver::find($request->driver_id);
            }
        }

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.'
            ], 404);
        }

        DriverDeclinedOrder::firstOrCreate(
            [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
            ],
            [
                'reason' => $request->input('reason', 'Driver passed on request'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Order declined and removed from your upcoming requests list.',
        ]);
    }

    /**
     * Get Driver Deliveries grouped / filtered by tabs:
     * - all
     * - ongoing (assigned, picked_up, on_the_way)
     * - completed (delivered)
     */
    public function myDeliveries(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $driver = $authUser->driver;
        if (!$driver) {
            if ($request->filled('driver_id') && ($authUser->isSuperAdmin() || $authUser->isBranchAdmin())) {
                $driver = Driver::find($request->driver_id);
            }
        }

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found.'], 404);
        }

        $tab = $request->input('tab', 'all'); // 'all', 'upcoming', 'ongoing', 'completed'

        // Counts for tab badges
        $declinedOrderIds = DriverDeclinedOrder::where('driver_id', $driver->id)->pluck('order_id')->toArray();
        $upcomingCount = Order::where('order_type', 'delivery')
            ->whereNull('assigned_driver_id')
            ->whereIn('order_status', ['pending', 'accepted', 'preparing', 'ready'])
            ->whereNotIn('id', $declinedOrderIds)
            ->when($driver->branch_id, fn($q) => $q->where('branch_id', $driver->branch_id))
            ->count();

        $ongoingCount = Delivery::where('driver_id', $driver->id)
            ->whereIn('delivery_status', ['assigned', 'picked_up', 'on_the_way'])
            ->count();

        $completedCount = Delivery::where('driver_id', $driver->id)
            ->where('delivery_status', 'delivered')
            ->count();

        $allCount = Delivery::where('driver_id', $driver->id)->count();

        $tabCounts = [
            'all' => $allCount,
            'upcoming' => $upcomingCount,
            'ongoing' => $ongoingCount,
            'completed' => $completedCount,
        ];

        // If tab is upcoming, return formatted unassigned orders
        if ($tab === 'upcoming') {
            if (!$driver->is_online) {
                return response()->json([
                    'tab_counts' => $tabCounts,
                    'current_tab' => $tab,
                    'is_online' => false,
                    'message' => 'Driver is currently offline. Please turn online status ON to view upcoming delivery requests.',
                    'deliveries' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => (int) $request->input('per_page', 15),
                        'total' => 0,
                        'data' => [],
                    ],
                ]);
            }

            $upcomingQuery = Order::with(['items.menuItem', 'branch', 'address'])
                ->where('order_type', 'delivery')
                ->whereNull('assigned_driver_id')
                ->whereIn('order_status', ['pending', 'accepted', 'preparing', 'ready'])
                ->whereNotIn('id', $declinedOrderIds)
                ->when($driver->branch_id, fn($q) => $q->where('branch_id', $driver->branch_id))
                ->latest();

            $upcomingOrders = $upcomingQuery->paginate($request->input('per_page', 15));

            $formattedUpcoming = $upcomingOrders->getCollection()->map(function ($order) {
                $firstItem = $order->items->first();
                $menuItem = $firstItem?->menuItem;
                $itemsCount = $order->items->count();
                $earnings = (float) $order->delivery_fee;

                return [
                    'id' => null,
                    'order_id' => $order->id,
                    'driver_id' => null,
                    'delivery_status' => 'pending',
                    'order_number' => $order->order_number,
                    'order_status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'category_tag' => $menuItem?->category?->name ?? 'Special',
                    'title' => $menuItem ? $menuItem->name . ($itemsCount > 1 ? " + " . ($itemsCount - 1) . " more" : "") : 'Delivery Package',
                    'image_url' => $menuItem?->image_url ?? null,
                    'earnings' => $earnings,
                    'formatted_earnings' => '£' . number_format($earnings, 2),
                    'delivery_fee' => (float) $order->delivery_fee,
                    'formatted_delivery_fee' => $order->delivery_fee > 0 ? '£' . number_format((float) $order->delivery_fee, 2) : 'Free',
                    'rider_tip' => (float) $order->rider_tip,
                    'formatted_rider_tip' => '£' . number_format((float) $order->rider_tip, 2),
                    'total_order_amount' => (float) $order->total,
                    'formatted_total_amount' => '£' . number_format((float) $order->total, 2),
                    'pickup_location' => $order->branch ? ($order->branch->address ?? $order->branch->name) : 'Pacinos Branch',
                    'delivery_location' => $order->delivery_address ?? ($order->address ? $order->address->address_line_1 . ', ' . $order->address->postcode : 'Customer Location'),
                    'customer_name' => $order->customer_name ?? $order->user?->name ?? 'Valued Customer',
                    'customer_phone' => $order->customer_phone ?? $order->user?->phone ?? 'N/A',
                    'distance_km' => $order->calculateDistanceKm(),
                    'distance_remaining' => $order->calculateDistanceKm() . ' km Remaining',
                    'time_remaining_minutes' => $order->calculateRemainingMinutes(),
                    'time_remaining' => $order->calculateRemainingMinutes() . ' mins Remaining',
                    'delivery_time_formatted' => $order->estimated_delivery_time ? Carbon::parse($order->estimated_delivery_time)->format('l, M d, h:i A') : $order->created_at->format('l, M d, h:i A'),
                    'created_at' => $order->created_at->toIso8601String(),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->menuItem?->name ?? 'Menu Item',
                            'quantity' => $item->quantity,
                            'price' => (float) $item->unit_price,
                            'image_url' => $item->menuItem?->image_url,
                        ];
                    }),
                    'order' => $order,
                ];
            });

            return response()->json([
                'tab_counts' => $tabCounts,
                'current_tab' => $tab,
                'is_online' => (bool) $driver->is_online,
                'deliveries' => [
                    'current_page' => $upcomingOrders->currentPage(),
                    'last_page' => $upcomingOrders->lastPage(),
                    'per_page' => $upcomingOrders->perPage(),
                    'total' => $upcomingOrders->total(),
                    'data' => $formattedUpcoming,
                ],
            ]);
        }

        $query = Delivery::with([
            'order.items.menuItem',
            'order.branch',
            'order.user.defaultAddress',
            'order.user.address',
            'order.address',
            'driver.user',
            'user.defaultAddress',
            'user.address',
        ])
            ->where('driver_id', $driver->id)
            ->latest();

        if ($tab === 'ongoing') {
            $query->whereIn('delivery_status', ['assigned', 'picked_up', 'on_the_way']);
        } elseif ($tab === 'completed') {
            $query->where('delivery_status', 'delivered');
        }

        $deliveries = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'tab_counts' => $tabCounts,
            'current_tab' => $tab,
            'is_online' => (bool) $driver->is_online,
            'deliveries' => $deliveries,
        ]);
    }
}
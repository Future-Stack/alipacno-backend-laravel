<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    /**
     * Display a listing of drivers.
     */
    public function index(Request $request)
    {
        $query = Driver::with(['branch', 'user', 'deliveries' => function ($q) {
            $q->whereIn('delivery_status', ['assigned', 'picked_up', 'on_the_way']);
        }]);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
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
     * Store a newly created driver.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'vehicle_type' => 'nullable|string|max:100',
            'license_number' => 'nullable|string|max:100',
            'kyc_status' => 'nullable|in:pending,submitted,approved,rejected',
            'is_online' => 'nullable|boolean',
            'status' => 'nullable|in:available,on_delivery,offline',
        ]);

        $validated['vehicle_type'] = $validated['vehicle_type'] ?? 'Motorcycle';
        $validated['kyc_status'] = $validated['kyc_status'] ?? 'pending';
        $validated['is_online'] = $validated['is_online'] ?? false;
        $validated['status'] = $validated['status'] ?? 'available';

        $driver = Driver::create($validated);

        return response()->json($driver->load(['branch', 'user']), 201);
    }

    /**
     * Display the specified driver.
     */
    public function show(Driver $driver)
    {
        return response()->json($driver->load(['branch', 'user', 'deliveries.order']));
    }

    /**
     * Update the specified driver.
     */
    public function update(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'vehicle_type' => 'sometimes|string|max:100',
            'license_number' => 'nullable|string|max:100',
            'kyc_status' => 'sometimes|in:pending,submitted,approved,rejected',
            'is_online' => 'sometimes|boolean',
            'status' => 'sometimes|in:available,on_delivery,offline',
        ]);

        $driver->update($validated);

        return response()->json($driver->load(['branch', 'user']));
    }

    /**
     * Remove the specified driver.
     */
    public function destroy(Driver $driver)
    {
        $driver->delete();

        return response()->json(null, 204);
    }

    /**
     * Update driver availability status.
     */
    public function updateStatus(Request $request, Driver $driver)
    {
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
     * Submit driver KYC documents (Driver).
     */
    public function submitKyc(Request $request)
    {
        $user = $request->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'vehicle_type' => 'sometimes|string|max:100',
            'license_number' => 'sometimes|string|max:100',
        ]);

        $validated['kyc_status'] = 'submitted';

        $driver->update($validated);

        return response()->json([
            'message' => 'KYC documents submitted successfully. Waiting for admin approval.',
            'kyc_status' => 'submitted',
            'driver' => $driver->fresh(['branch', 'user']),
        ]);
    }

    /**
     * Update driver KYC approval status (Super Admin / Admin).
     */
    public function updateKycStatus(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'kyc_status' => 'required|in:pending,submitted,approved,rejected',
        ]);

        $updateData = ['kyc_status' => $validated['kyc_status']];
        if ($validated['kyc_status'] === 'approved') {
            $updateData['is_online'] = true;
        } elseif ($validated['kyc_status'] === 'rejected' || $validated['kyc_status'] === 'pending') {
            $updateData['is_online'] = false;
        }

        $driver->update($updateData);

        return response()->json([
            'message' => "Driver KYC status updated to '{$validated['kyc_status']}' successfully.",
            'driver' => $driver->fresh(['branch', 'user']),
        ]);
    }
}
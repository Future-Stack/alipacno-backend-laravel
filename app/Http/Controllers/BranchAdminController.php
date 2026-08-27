<?php

namespace App\Http\Controllers;

use App\Models\BranchAdmin;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BranchAdminController extends Controller
{
    /**
     * Display a listing of branch admins.
     */
    public function index(Request $request)
    {
        $query = BranchAdmin::with(['branch', 'user']);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'name', 'email', 'phone', 'status', 'last_login', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created branch admin in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:branch_admins,email',
            'phone' => 'nullable|string|max:50|unique:branch_admins,phone',
            'password' => 'required|string|min:8',
            'avatar' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        $passwordHash = Hash::make($validated['password']);

        // Find 'Branch Manager' role if present
        $role = Role::where('name', 'Branch Manager')->first();

        // Create or update linked User account for unified authentication
        $user = User::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'password' => $passwordHash,
                'user_type' => 'branch_admin',
                'role_id' => $role?->id,
                'status' => $validated['status'],
                'email_verified_at' => now(),
            ]
        );

        $validated['user_id'] = $user->id;
        $validated['password'] = $passwordHash;

        $admin = BranchAdmin::create($validated);

        return response()->json($admin->load(['branch', 'user']), 201);
    }

    /**
     * Display the specified branch admin.
     */
    public function show(BranchAdmin $branchAdmin)
    {
        return response()->json($branchAdmin->load(['branch', 'user']));
    }

    /**
     * Update the specified branch admin in storage.
     */
    public function update(Request $request, BranchAdmin $branchAdmin)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:branch_admins,email,' . $branchAdmin->id,
            'phone' => 'nullable|string|max:50|unique:branch_admins,phone,' . $branchAdmin->id,
            'password' => 'nullable|string|min:8',
            'avatar' => 'nullable|string|max:255',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $branchAdmin->update($validated);

        if ($branchAdmin->user_id) {
            $userUpdateData = array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'status' => $validated['status'] ?? null,
            ]);
            if (!empty($validated['password'])) {
                $userUpdateData['password'] = $validated['password'];
            }
            if (!empty($userUpdateData)) {
                User::where('id', $branchAdmin->user_id)->update($userUpdateData);
            }
        }

        return response()->json($branchAdmin->load(['branch', 'user']));
    }

    /**
     * Remove the specified branch admin from storage.
     */
    public function destroy(BranchAdmin $branchAdmin)
    {
        if ($branchAdmin->user_id) {
            User::where('id', $branchAdmin->user_id)->delete();
        }

        $branchAdmin->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle branch admin status.
     */
    public function toggleStatus(BranchAdmin $branchAdmin)
    {
        $newStatus = $branchAdmin->status === 'active' ? 'inactive' : 'active';
        $branchAdmin->update(['status' => $newStatus]);

        if ($branchAdmin->user_id) {
            User::where('id', $branchAdmin->user_id)->update(['status' => $newStatus]);
        }

        return response()->json([
            'success' => true,
            'message' => "Branch admin status changed to {$newStatus}.",
            'data' => $branchAdmin->load(['branch', 'user']),
        ]);
    }
}
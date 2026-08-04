<?php

namespace App\Http\Controllers;

use App\Models\BranchAdmin;
use Illuminate\Http\Request;

class BranchAdminController extends Controller
{
    /**
     * Display a listing of branch admins.
     */
    public function index(Request $request)
    {
        $query = BranchAdmin::with('branch');

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

        $admin = BranchAdmin::create($validated);

        return response()->json($admin->load('branch'), 201);
    }

    /**
     * Display the specified branch admin.
     */
    public function show(BranchAdmin $branchAdmin)
    {
        return response()->json($branchAdmin->load('branch'));
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

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $branchAdmin->update($validated);

        return response()->json($branchAdmin->load('branch'));
    }

    /**
     * Remove the specified branch admin from storage.
     */
    public function destroy(BranchAdmin $branchAdmin)
    {
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

        return response()->json([
            'success' => true,
            'message' => "Branch admin status changed to {$newStatus}.",
            'data' => $branchAdmin->load('branch'),
        ]);
    }
}
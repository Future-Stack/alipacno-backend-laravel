<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Display a listing of staff members.
     */
    public function index(Request $request)
    {
        $query = Staff::with(['branch', 'role']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:50',
            'role_id' => 'nullable|exists:roles,id',
            'salary' => 'nullable|numeric|min:0',
            'commission' => 'nullable|numeric|min:0|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'nullable|in:active,inactive,on_leave',
        ]);

        $staff = Staff::create($validated);

        return response()->json($staff->load(['branch', 'role']), 201);
    }

    /**
     * Display the specified staff member.
     */
    public function show(Staff $staff)
    {
        return response()->json($staff->load(['branch', 'role', 'attendances']));
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'sometimes|string|max:50',
            'role_id' => 'nullable|exists:roles,id',
            'salary' => 'nullable|numeric|min:0',
            'commission' => 'nullable|numeric|min:0|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'sometimes|in:active,inactive,on_leave',
        ]);

        $staff->update($validated);

        return response()->json($staff->load(['branch', 'role']));
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(Staff $staff)
    {
        $staff->delete();

        return response()->json(null, 204);
    }
}
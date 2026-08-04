<?php

namespace App\Http\Controllers;

use App\Models\HqAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HqAdminController extends Controller
{
    /**
     * Display a listing of HQ admins.
     */
    public function index(Request $request)
    {
        $query = HqAdmin::with(['user']);

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
     * Store a newly created HQ admin.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:hq_admins,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8',
            'avatar' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $passwordHash = Hash::make($validated['password']);

        // Create linked User account for HQ Admin auth if not existing
        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'password' => $passwordHash,
                'user_type' => 'hq_admin',
                'status' => $validated['status'] ?? 'active',
            ]
        );

        $hqAdmin = HqAdmin::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $passwordHash,
            'avatar' => $validated['avatar'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json($hqAdmin->load(['user']), 201);
    }

    /**
     * Display the specified HQ admin.
     */
    public function show(HqAdmin $hqAdmin)
    {
        return response()->json($hqAdmin->load(['user']));
    }

    /**
     * Update the specified HQ admin.
     */
    public function update(Request $request, HqAdmin $hqAdmin)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:hq_admins,email,' . $hqAdmin->id,
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8',
            'avatar' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $hqAdmin->update($validated);

        if ($hqAdmin->user_id) {
            User::where('id', $hqAdmin->user_id)->update(array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'] ?? null,
                'status' => $validated['status'] ?? null,
            ]));
        }

        return response()->json($hqAdmin->load(['user']));
    }

    /**
     * Remove the specified HQ admin.
     */
    public function destroy(HqAdmin $hqAdmin)
    {
        $hqAdmin->delete();

        return response()->json(null, 204);
    }
}
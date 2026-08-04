<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request)
    {
        $query = Role::with('permissions')->withCount(['permissions', 'users']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'name', 'created_at'];

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
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = DB::transaction(function () use ($validated) {
            $role = Role::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            if (!empty($validated['permissions'])) {
                $role->permissions()->sync($validated['permissions']);
            }

            return $role;
        });

        return response()->json($role->load('permissions')->loadCount(['permissions', 'users']), 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        return response()->json($role->load(['permissions', 'users'])->loadCount(['permissions', 'users']));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::transaction(function () use ($request, $validated, $role) {
            $role->update(array_intersect_key($validated, array_flip(['name', 'description'])));

            if ($request->has('permissions')) {
                $role->permissions()->sync($validated['permissions'] ?? []);
            }
        });

        return response()->json($role->load('permissions')->loadCount(['permissions', 'users']));
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role because it is currently assigned to one or more users.',
            ], 422);
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Sync permission IDs to a role.
     */
    public function syncPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($validated['permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Permissions synchronized successfully.',
            'data' => $role->load('permissions')->loadCount(['permissions', 'users']),
        ]);
    }

    /**
     * Assign new permission IDs to a role without detaching existing ones.
     */
    public function assignPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->syncWithoutDetaching($validated['permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully.',
            'data' => $role->load('permissions')->loadCount(['permissions', 'users']),
        ]);
    }
}
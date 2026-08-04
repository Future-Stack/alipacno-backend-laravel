<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request)
    {
        $query = Permission::query();

        if ($request->filled('module')) {
            $query->byModule($request->module);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->boolean('with_roles')) {
            $query->withCount('roles');
        }

        $sortBy = $request->input('sort_by', 'module');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'name', 'module', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('module')->orderBy('name');
        }

        if ($request->boolean('grouped') || $request->boolean('by_module')) {
            $permissions = $query->get()->groupBy('module');
            return response()->json(['data' => $permissions]);
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created permission in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'module' => 'required|string|max:255',
        ]);

        $permission = Permission::create($validated);

        return response()->json($permission, 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission)
    {
        return response()->json($permission->load('roles'));
    }

    /**
     * Update the specified permission in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->ignore($permission->id),
            ],
            'module' => 'sometimes|required|string|max:255',
        ]);

        $permission->update($validated);

        return response()->json($permission);
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroy(Permission $permission)
    {
        $permission->roles()->detach();
        $permission->delete();

        return response()->json(null, 204);
    }

    /**
     * Get a list of all distinct permission modules.
     */
    public function modules()
    {
        $modules = Permission::select('module')
            ->distinct()
            ->whereNotNull('module')
            ->orderBy('module')
            ->pluck('module');

        return response()->json(['data' => $modules]);
    }

    /**
     * Bulk store permissions for a specific module.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'module' => 'required|string|max:255',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'required|string|max:255',
        ]);

        $module = $validated['module'];
        $created = [];

        foreach ($validated['permissions'] as $permName) {
            $permission = Permission::firstOrCreate([
                'name' => $permName,
            ], [
                'module' => $module,
            ]);

            $created[] = $permission;
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' permissions processed for module ' . $module,
            'data' => $created,
        ], 201);
    }
}
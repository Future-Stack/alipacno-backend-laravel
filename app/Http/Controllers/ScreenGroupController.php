<?php

namespace App\Http\Controllers;

use App\Models\ScreenGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScreenGroupController extends Controller
{
    /**
     * Display a listing of screen groups.
     */
    public function index(Request $request)
    {
        $query = ScreenGroup::with(['branch', 'screens'])->withCount('screens');

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

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
     * Store a newly created screen group in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'screen_ids' => 'nullable|array',
            'screen_ids.*' => 'exists:digital_screens,id',
        ]);

        $group = DB::transaction(function () use ($validated) {
            $group = ScreenGroup::create([
                'branch_id' => $validated['branch_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            if (!empty($validated['screen_ids'])) {
                $group->screens()->sync($validated['screen_ids']);
            }

            return $group;
        });

        return response()->json($group->load(['branch', 'screens'])->loadCount('screens'), 201);
    }

    /**
     * Display the specified screen group.
     */
    public function show(ScreenGroup $screenGroup)
    {
        return response()->json($screenGroup->load(['branch', 'screens'])->loadCount('screens'));
    }

    /**
     * Update the specified screen group in storage.
     */
    public function update(Request $request, ScreenGroup $screenGroup)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'screen_ids' => 'nullable|array',
            'screen_ids.*' => 'exists:digital_screens,id',
        ]);

        DB::transaction(function () use ($request, $validated, $screenGroup) {
            $screenGroup->update(array_intersect_key($validated, array_flip(['branch_id', 'name', 'description'])));

            if ($request->has('screen_ids')) {
                $screenGroup->screens()->sync($validated['screen_ids'] ?? []);
            }
        });

        return response()->json($screenGroup->load(['branch', 'screens'])->loadCount('screens'));
    }

    /**
     * Remove the specified screen group from storage.
     */
    public function destroy(ScreenGroup $screenGroup)
    {
        DB::transaction(function () use ($screenGroup) {
            $screenGroup->screens()->detach();
            $screenGroup->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Sync screen IDs to a screen group.
     */
    public function syncScreens(Request $request, ScreenGroup $screenGroup)
    {
        $validated = $request->validate([
            'screen_ids' => 'required|array',
            'screen_ids.*' => 'exists:digital_screens,id',
        ]);

        $screenGroup->screens()->sync($validated['screen_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Digital screens synchronized successfully.',
            'data' => $screenGroup->load(['branch', 'screens'])->loadCount('screens'),
        ]);
    }

    /**
     * Assign new screen IDs to a screen group without detaching existing ones.
     */
    public function assignScreens(Request $request, ScreenGroup $screenGroup)
    {
        $validated = $request->validate([
            'screen_ids' => 'required|array|min:1',
            'screen_ids.*' => 'exists:digital_screens,id',
        ]);

        $screenGroup->screens()->syncWithoutDetaching($validated['screen_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Digital screens assigned successfully.',
            'data' => $screenGroup->load(['branch', 'screens'])->loadCount('screens'),
        ]);
    }
}
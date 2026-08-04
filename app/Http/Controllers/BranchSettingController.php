<?php

namespace App\Http\Controllers;

use App\Models\BranchSetting;
use Illuminate\Http\Request;

class BranchSettingController extends Controller
{
    /**
     * Display a listing of branch settings.
     */
    public function index(Request $request)
    {
        $query = BranchSetting::with('branch');

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'branch_id', 'tax_rate', 'minimum_order', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('id', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created branch setting in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id|unique:branch_settings,branch_id',
            'tax_rate' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'delivery_radius' => 'nullable|numeric|min:0',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        $setting = BranchSetting::create($validated);

        return response()->json($setting->load('branch'), 201);
    }

    /**
     * Display the specified branch setting.
     */
    public function show(BranchSetting $branchSetting)
    {
        return response()->json($branchSetting->load('branch'));
    }

    /**
     * Update the specified branch setting in storage.
     */
    public function update(Request $request, BranchSetting $branchSetting)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id|unique:branch_settings,branch_id,' . $branchSetting->id,
            'tax_rate' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'delivery_radius' => 'nullable|numeric|min:0',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        $branchSetting->update($validated);

        return response()->json($branchSetting->load('branch'));
    }

    /**
     * Remove the specified branch setting from storage.
     */
    public function destroy(BranchSetting $branchSetting)
    {
        $branchSetting->delete();

        return response()->json(null, 204);
    }

    /**
     * Get setting for a specific branch.
     */
    public function getByBranch($branchId)
    {
        $setting = BranchSetting::with('branch')
            ->where('branch_id', $branchId)
            ->firstOrFail();

        return response()->json($setting);
    }
}
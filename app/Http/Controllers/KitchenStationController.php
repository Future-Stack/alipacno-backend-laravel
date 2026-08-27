<?php

namespace App\Http\Controllers;

use App\Models\KitchenStation;
use Illuminate\Http\Request;

class KitchenStationController extends Controller
{
    /**
     * Display a listing of kitchen stations.
     */
    public function index(Request $request)
    {
        $query = KitchenStation::with('branch')->withCount(['orders' => function ($q) {
            $q->whereIn('status', ['pending', 'preparing']);
        }]);

        $authUser = $request->user() ?? auth('sanctum')->user();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        } elseif ($authUser && $authUser->hasRole(['branch_admin', 'cashier', 'chef', 'waiter', 'staff', 'Branch Manager', 'Chef'])) {
            $userBranchId = $authUser->branch_id 
                ?? \App\Models\BranchAdmin::where('email', $authUser->email)->value('branch_id')
                ?? \App\Models\Staff::where('email', $authUser->email)->value('branch_id');
            if ($userBranchId) {
                $query->where('branch_id', $userBranchId);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('display_order', 'asc')->orderBy('id', 'asc');

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created kitchen station.
     */
    public function store(Request $request)
    {
        $authUser = $request->user() ?? auth('sanctum')->user();
        if (!$request->filled('branch_id') && $authUser) {
            $userBranchId = $authUser->branch_id 
                ?? \App\Models\BranchAdmin::where('email', $authUser->email)->value('branch_id')
                ?? \App\Models\Staff::where('email', $authUser->email)->value('branch_id');
            if ($userBranchId) {
                $request->merge(['branch_id' => $userBranchId]);
            }
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:1',
            'status' => 'nullable|in:active,inactive',
        ]);

        $station = KitchenStation::create($validated);

        return response()->json($station->load('branch'), 201);
    }

    /**
     * Display the specified kitchen station.
     */
    public function show(KitchenStation $kitchenStation)
    {
        return response()->json($kitchenStation->load(['branch', 'orders.order.items']));
    }

    /**
     * Update the specified kitchen station.
     */
    public function update(Request $request, KitchenStation $kitchenStation)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:1',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $kitchenStation->update($validated);

        return response()->json($kitchenStation->load('branch'));
    }

    /**
     * Remove the specified kitchen station.
     */
    public function destroy(KitchenStation $kitchenStation)
    {
        $kitchenStation->delete();

        return response()->json(null, 204);
    }
}
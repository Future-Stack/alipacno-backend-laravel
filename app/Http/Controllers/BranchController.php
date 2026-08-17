<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(Request $request)
    {
        $query = Branch::with(['restaurant']);

        if ($request->boolean('is_active')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 20)));
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'delivery_radius' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        $branch = Branch::create($validated);


        return response()->json([
            'status'=>true,
            'message'=>'Branch updated successfully',
            'data'=>$branch
        ],201);
    }

    /**
     * Display the specified branch.
     */
    public function show(Branch $branch)
    {

        return response()->json([
            'status'=>true,
            'message'=>'Branch fetched successfully',
            'data'=> $branch->load([ 'restaurant'])
        ]);
    }

    /**
     * Update the specified branch in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'restaurant_id' => 'sometimes|exists:restaurants,id',
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_active' => 'sometimes|boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'delivery_radius' => 'nullable|numeric|min:0',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        $branch->update($validated);

        return response()->json([
            'status'=>true,
            'message'=>'Branch updated successfully',
            'data'=>$branch
        ]);
    }

    /**
     * Remove the specified branch from storage.
     */
    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->json([
            'status' => true,
            'message' => 'Branch Deleted'
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\CustomerSegment;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerSegmentController extends Controller
{
    /**
     * Display a listing of customer segments.
     */
    public function index(Request $request)
    {
        $query = CustomerSegment::query();

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
     * Store a newly created customer segment in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:customer_segments,name',
            'conditions' => 'required|array',
        ]);

        $segment = CustomerSegment::create($validated);

        return response()->json($segment, 201);
    }

    /**
     * Display the specified customer segment.
     */
    public function show(CustomerSegment $customerSegment)
    {
        return response()->json($customerSegment);
    }

    /**
     * Update the specified customer segment in storage.
     */
    public function update(Request $request, CustomerSegment $customerSegment)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:customer_segments,name,' . $customerSegment->id,
            'conditions' => 'sometimes|required|array',
        ]);

        $customerSegment->update($validated);

        return response()->json($customerSegment);
    }

    /**
     * Remove the specified customer segment from storage.
     */
    public function destroy(CustomerSegment $customerSegment)
    {
        $customerSegment->delete();

        return response()->json(null, 204);
    }

    /**
     * Get customers matching segment rule conditions.
     */
    public function getCustomers(Request $request, CustomerSegment $customerSegment)
    {
        $conditions = $customerSegment->conditions ?? [];
        $query = User::query();

        // Apply conditions matching user model attributes
        if (!empty($conditions['user_type'])) {
            $query->where('user_type', $conditions['user_type']);
        }

        if (!empty($conditions['status'])) {
            $query->where('status', $conditions['status']);
        }

        if (!empty($conditions['search'])) {
            $search = $conditions['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }
}
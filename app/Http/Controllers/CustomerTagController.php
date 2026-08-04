<?php

namespace App\Http\Controllers;

use App\Models\CustomerTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerTagController extends Controller
{
    /**
     * Display a listing of customer tags.
     */
    public function index(Request $request)
    {
        $query = CustomerTag::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'name', 'color', 'created_at'];

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
     * Store a newly created customer tag in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:customer_tags,name',
            'color' => 'nullable|string|max:20',
        ]);

        if (empty($validated['color'])) {
            $validated['color'] = '#000000';
        }

        $tag = CustomerTag::create($validated);

        return response()->json($tag, 201);
    }

    /**
     * Display the specified customer tag.
     */
    public function show(CustomerTag $customerTag)
    {
        return response()->json($customerTag);
    }

    /**
     * Update the specified customer tag in storage.
     */
    public function update(Request $request, CustomerTag $customerTag)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:customer_tags,name,' . $customerTag->id,
            'color' => 'nullable|string|max:20',
        ]);

        $customerTag->update($validated);

        return response()->json($customerTag);
    }

    /**
     * Remove the specified customer tag from storage.
     */
    public function destroy(CustomerTag $customerTag)
    {
        $customerTag->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk store multiple customer tags.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'tags' => 'required|array|min:1',
            'tags.*.name' => 'required|string|max:255|unique:customer_tags,name',
            'tags.*.color' => 'nullable|string|max:20',
        ]);

        $created = DB::transaction(function () use ($validated) {
            $records = [];
            foreach ($validated['tags'] as $tagData) {
                $records[] = CustomerTag::create([
                    'name' => $tagData['name'],
                    'color' => $tagData['color'] ?? '#000000',
                ]);
            }
            return $records;
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' customer tags created successfully.',
            'data' => $created,
        ], 201);
    }
}
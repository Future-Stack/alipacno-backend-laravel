<?php

namespace App\Http\Controllers;

use App\Models\CustomerNote;
use Illuminate\Http\Request;

class CustomerNoteController extends Controller
{
    /**
     * Display a listing of customer notes.
     */
    public function index(Request $request)
    {
        $query = CustomerNote::with(['user', 'staff']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('note', 'like', "%{$search}%");
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created customer note.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'staff_id' => 'nullable|exists:users,id',
            'note' => 'required|string',
        ]);

        if (!isset($validated['staff_id']) && $request->user()) {
            $validated['staff_id'] = $request->user()->id;
        }

        $customerNote = CustomerNote::create($validated);

        return response()->json($customerNote->load(['user', 'staff']), 201);
    }

    /**
     * Display the specified customer note.
     */
    public function show(CustomerNote $customerNote)
    {
        return response()->json($customerNote->load(['user', 'staff']));
    }

    /**
     * Update the specified customer note.
     */
    public function update(Request $request, CustomerNote $customerNote)
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'staff_id' => 'nullable|exists:users,id',
            'note' => 'sometimes|string',
        ]);

        $customerNote->update($validated);

        return response()->json($customerNote->load(['user', 'staff']));
    }

    /**
     * Remove the specified customer note.
     */
    public function destroy(CustomerNote $customerNote)
    {
        $customerNote->delete();

        return response()->json(null, 204);
    }
}
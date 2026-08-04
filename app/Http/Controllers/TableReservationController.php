<?php

namespace App\Http\Controllers;

use App\Models\TableReservation;
use Illuminate\Http\Request;

class TableReservationController extends Controller
{
    /**
     * Display a listing of table reservations.
     */
    public function index(Request $request)
    {
        $query = TableReservation::with(['restaurant', 'table', 'user']);

        if ($request->filled('restaurant_id')) {
            $query->forRestaurant($request->restaurant_id);
        }

        if ($request->filled('table_id')) {
            $query->forTable($request->table_id);
        }

        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->input('start_date'), $request->input('end_date'));
        }

        $sortBy = $request->input('sort_by', 'reservation_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'reservation_date', 'reservation_time', 'guest_count', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('reservation_date', 'desc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created table reservation in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'table_id' => 'required|exists:restaurant_tables,id',
            'user_id' => 'nullable|exists:users,id',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required',
            'guest_count' => 'required|integer|min:1',
            'status' => 'nullable|string|in:pending,confirmed,cancelled,completed',
        ]);

        if (empty($validated['user_id'])) {
            $validated['user_id'] = auth()->id();
        }

        if (empty($validated['status'])) {
            $validated['status'] = 'pending';
        }

        $reservation = TableReservation::create($validated);

        return response()->json($reservation->load(['restaurant', 'table', 'user']), 201);
    }

    /**
     * Display the specified table reservation.
     */
    public function show(TableReservation $tableReservation)
    {
        return response()->json($tableReservation->load(['restaurant', 'table', 'user']));
    }

    /**
     * Update the specified table reservation in storage.
     */
    public function update(Request $request, TableReservation $tableReservation)
    {
        $validated = $request->validate([
            'restaurant_id' => 'sometimes|required|exists:restaurants,id',
            'table_id' => 'sometimes|required|exists:restaurant_tables,id',
            'user_id' => 'nullable|exists:users,id',
            'reservation_date' => 'sometimes|required|date',
            'reservation_time' => 'sometimes|required',
            'guest_count' => 'sometimes|required|integer|min:1',
            'status' => 'sometimes|required|string|in:pending,confirmed,cancelled,completed',
        ]);

        $tableReservation->update($validated);

        return response()->json($tableReservation->load(['restaurant', 'table', 'user']));
    }

    /**
     * Remove the specified table reservation from storage.
     */
    public function destroy(TableReservation $tableReservation)
    {
        $tableReservation->delete();

        return response()->json(null, 204);
    }

    /**
     * Update table reservation status.
     */
    public function updateStatus(Request $request, TableReservation $tableReservation)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,cancelled,completed',
        ]);

        $tableReservation->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => "Reservation status changed to {$validated['status']}.",
            'data' => $tableReservation->load(['restaurant', 'table', 'user']),
        ]);
    }
}
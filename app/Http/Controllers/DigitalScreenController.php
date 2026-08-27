<?php

namespace App\Http\Controllers;

use App\Models\DigitalScreen;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DigitalScreenController extends Controller
{
    /**
     * Display a listing of digital screens.
     */
    public function index(Request $request)
    {
        $query = DigitalScreen::with(['branch', 'screenGroup']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('screen_group_id')) {
            $query->where('screen_group_id', $request->screen_group_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('screen_name', 'like', "%{$search}%")
                  ->orWhere('device_uuid', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created digital screen.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'screen_name' => 'required|string|max:255',
            'screen_group_id' => 'nullable|exists:screen_groups,id',
            'device_uuid' => 'nullable|string|max:255|unique:digital_screens,device_uuid',
            'resolution' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:online,offline,maintenance',
        ]);

        if (empty($validated['device_uuid'])) {
            $validated['device_uuid'] = (string) Str::uuid();
        }

        $screen = DigitalScreen::create($validated);

        return response()->json($screen->load(['branch', 'screenGroup']), 201);
    }

    /**
     * Display the specified digital screen.
     */
    public function show(DigitalScreen $digitalScreen)
    {
        return response()->json([
            'success' => true,
            'data' => $digitalScreen->load(['branch', 'screenGroup', 'schedules.playlist', 'impressions']),
        ]);
    }

    /**
     * Update the specified digital screen.
     */
    public function update(Request $request, DigitalScreen $digitalScreen)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'screen_name' => 'sometimes|string|max:255',
            'screen_group_id' => 'nullable|exists:screen_groups,id',
            'device_uuid' => 'sometimes|string|max:255|unique:digital_screens,device_uuid,' . $digitalScreen->id,
            'resolution' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'status' => 'sometimes|in:online,offline,maintenance',
        ]);

        $digitalScreen->update($validated);

        return response()->json($digitalScreen->load(['branch', 'screenGroup']));
    }

    /**
     * Remove the specified digital screen.
     */
    public function destroy(DigitalScreen $digitalScreen)
    {
        $digitalScreen->delete();

        return response()->json(null, 204);
    }

    /**
     * Heartbeat / Ping sync endpoint for signage hardware display terminal.
     */
    public function sync(DigitalScreen $digitalScreen)
    {
        $digitalScreen->update([
            'status' => 'online',
            'last_sync' => now(),
        ]);

        return response()->json([
            'synced' => true,
            'screen' => $digitalScreen->load(['branch', 'screenGroup']),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
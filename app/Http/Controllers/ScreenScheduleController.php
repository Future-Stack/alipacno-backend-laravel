<?php

namespace App\Http\Controllers;

use App\Models\ScreenSchedule;
use Illuminate\Http\Request;

class ScreenScheduleController extends Controller
{
    /**
     * Display a listing of screen schedules.
     */
    public function index(Request $request)
    {
        $query = ScreenSchedule::with(['screen', 'playlist']);

        if ($request->filled('screen_id')) {
            $query->forScreen($request->screen_id);
        }

        if ($request->filled('playlist_id')) {
            $query->forPlaylist($request->playlist_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('repeat_type')) {
            $query->where('repeat_type', $request->repeat_type);
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        $sortBy = $request->input('sort_by', 'start_date');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'start_date', 'end_date', 'priority', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('start_date', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created screen schedule in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'screen_id' => 'nullable',
            'playlist_id' => 'nullable',
            'playlist_title' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'repeat_type' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        // 1. Resolve or Auto-create Screen
        if (!empty($validated['screen_id'])) {
            $screen = \App\Models\DigitalScreen::find($validated['screen_id']);
            if (!$screen) {
                $branchId = \App\Models\Branch::value('id') ?: 1;
                $screen = \App\Models\DigitalScreen::create([
                    'branch_id' => $branchId,
                    'screen_name' => 'Main Counter Screen 1',
                    'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'resolution' => '1920 x 1080',
                    'location' => 'Main Counter',
                ]);
                $validated['screen_id'] = $screen->id;
            }
        } else {
            $screen = \App\Models\DigitalScreen::first();
            if (!$screen) {
                $branchId = \App\Models\Branch::value('id') ?: 1;
                $screen = \App\Models\DigitalScreen::create([
                    'branch_id' => $branchId,
                    'screen_name' => 'Main Counter Screen 1',
                    'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'resolution' => '1920 x 1080',
                    'location' => 'Main Counter',
                ]);
            }
            $validated['screen_id'] = $screen->id;
        }

        // 2. Resolve or Auto-create Playlist
        if (!empty($validated['playlist_id'])) {
            $playlist = \App\Models\ScreenPlaylist::find($validated['playlist_id']);
            if (!$playlist) {
                $playlist = \App\Models\ScreenPlaylist::create([
                    'title' => $validated['playlist_title'] ?? $validated['title'] ?? 'Epic Items Slideshow',
                    'description' => 'Automated playlist for screen schedule',
                ]);
                $validated['playlist_id'] = $playlist->id;
            }
        } else {
            $playlist = \App\Models\ScreenPlaylist::firstOrCreate(
                ['title' => $validated['playlist_title'] ?? $validated['title'] ?? 'Daily Special Items'],
                ['description' => 'Automatically generated playlist for schedule']
            );
            $validated['playlist_id'] = $playlist->id;
        }

        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        if (empty($validated['priority'])) {
            $validated['priority'] = 1;
        }

        // Clean up temporary helper fields before saving
        unset($validated['playlist_title'], $validated['title']);

        $schedule = ScreenSchedule::create($validated);

        return response()->json($schedule->load(['screen', 'playlist']), 201);
    }

    /**
     * Display the specified screen schedule.
     */
    public function show(ScreenSchedule $screenSchedule)
    {
        return response()->json($screenSchedule->load(['screen', 'playlist']));
    }

    /**
     * Update the specified screen schedule in storage.
     */
    public function update(Request $request, ScreenSchedule $screenSchedule)
    {
        $validated = $request->validate([
            'screen_id' => 'sometimes|required|exists:digital_screens,id',
            'playlist_id' => 'sometimes|required|exists:screen_playlists,id',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'start_time' => 'sometimes|required',
            'end_time' => 'sometimes|required',
            'repeat_type' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:1',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        $screenSchedule->update($validated);

        return response()->json($screenSchedule->load(['screen', 'playlist']));
    }

    /**
     * Remove the specified screen schedule from storage.
     */
    public function destroy(ScreenSchedule $screenSchedule)
    {
        $screenSchedule->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle schedule active / inactive status.
     */
    public function toggleStatus(ScreenSchedule $screenSchedule)
    {
        $newStatus = $screenSchedule->status === 'active' ? 'inactive' : 'active';
        $screenSchedule->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "Schedule status changed to {$newStatus}.",
            'data' => $screenSchedule->load(['screen', 'playlist']),
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications.
     */
    public function index(Request $request)
    {
        $userId = $request->input('user_id', $request->user()?->id);

        $query = Notification::with(['user', 'branch']);

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereNull('user_id');
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created notification.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:order,inventory,delivery,staff,marketing,system',
            'is_read' => 'nullable|boolean',
        ]);

        $notification = Notification::create($validated);

        return response()->json($notification->load(['user', 'branch']), 201);
    }

    /**
     * Display the specified notification & mark as read.
     */
    public function show(Notification $notification)
    {
        if (!$notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return response()->json($notification->load(['user', 'branch']));
    }

    /**
     * Update the specified notification status.
     */
    public function update(Request $request, Notification $notification)
    {
        $validated = $request->validate([
            'is_read' => 'required|boolean',
        ]);

        $notification->update($validated);

        return response()->json($notification->load(['user', 'branch']));
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(Notification $notification)
    {
        $notification->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark all notifications as read for current user/branch.
     */
    public function markAllAsRead(Request $request)
    {
        $userId = $request->input('user_id', $request->user()?->id);

        $query = Notification::where('is_read', false);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $count = $query->update(['is_read' => true]);

        return response()->json([
            'message' => "Successfully marked {$count} notifications as read.",
            'marked_count' => $count,
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Http\Request;

class NotificationSettingController extends Controller
{
    /**
     * Display a listing of notification settings.
     */
    public function index(Request $request)
    {
        $userId = $request->input('user_id', $request->user()?->id);

        $query = NotificationSetting::with(['user', 'branchAdmin']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($request->filled('branch_admin_id')) {
            $query->where('branch_admin_id', $request->branch_admin_id);
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store or update notification settings.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'branch_admin_id' => 'nullable|exists:branch_admins,id',
            'order_alert' => 'nullable|boolean',
            'branch_alert' => 'nullable|boolean',
            'low_stock_alert' => 'nullable|boolean',
            'driver_alert' => 'nullable|boolean',
            'marketing_report' => 'nullable|boolean',
            'daily_summary' => 'nullable|boolean',
            'email_notification' => 'nullable|boolean',
            'sms_notification' => 'nullable|boolean',
            'push_notification' => 'nullable|boolean',
        ]);

        if (empty($validated['user_id']) && empty($validated['branch_admin_id']) && $request->user()) {
            $validated['user_id'] = $request->user()->id;
        }

        $conditions = [];
        if (!empty($validated['user_id'])) {
            $conditions['user_id'] = $validated['user_id'];
        } elseif (!empty($validated['branch_admin_id'])) {
            $conditions['branch_admin_id'] = $validated['branch_admin_id'];
        } else {
            $conditions['id'] = 0; // fallback creation
        }

        $settings = NotificationSetting::updateOrCreate($conditions, $validated);

        return response()->json($settings->load(['user', 'branchAdmin']), 201);
    }

    /**
     * Display the specified notification setting.
     */
    public function show(NotificationSetting $notificationSetting)
    {
        return response()->json($notificationSetting->load(['user', 'branchAdmin']));
    }

    /**
     * Update the specified notification setting.
     */
    public function update(Request $request, NotificationSetting $notificationSetting)
    {
        $validated = $request->validate([
            'order_alert' => 'nullable|boolean',
            'branch_alert' => 'nullable|boolean',
            'low_stock_alert' => 'nullable|boolean',
            'driver_alert' => 'nullable|boolean',
            'marketing_report' => 'nullable|boolean',
            'daily_summary' => 'nullable|boolean',
            'email_notification' => 'nullable|boolean',
            'sms_notification' => 'nullable|boolean',
            'push_notification' => 'nullable|boolean',
        ]);

        $notificationSetting->update($validated);

        return response()->json($notificationSetting->load(['user', 'branchAdmin']));
    }

    /**
     * Remove the specified notification setting.
     */
    public function destroy(NotificationSetting $notificationSetting)
    {
        $notificationSetting->delete();

        return response()->json(null, 204);
    }
}
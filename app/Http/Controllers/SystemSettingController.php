<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemSettingController extends Controller
{
    /**
     * Display a listing of system settings.
     */
    public function index(Request $request)
    {
        if ($request->boolean('dictionary')) {
            $settings = SystemSetting::pluck('setting_value', 'setting_key');
            return response()->json(['data' => $settings]);
        }

        $query = SystemSetting::with('updater');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'setting_key');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'setting_key', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('setting_key', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created system setting in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'setting_key' => 'required|string|max:255|unique:system_settings,setting_key',
            'setting_value' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['updated_by'] = auth()->id();

        $setting = SystemSetting::create($validated);

        return response()->json($setting->load('updater'), 201);
    }

    /**
     * Display the specified system setting.
     */
    public function show(SystemSetting $systemSetting)
    {
        return response()->json($systemSetting->load('updater'));
    }

    /**
     * Display system setting by key string.
     */
    public function getByKey(string $key)
    {
        $setting = SystemSetting::where('setting_key', $key)->with('updater')->firstOrFail();

        return response()->json($setting);
    }

    /**
     * Update the specified system setting in storage.
     */
    public function update(Request $request, SystemSetting $systemSetting)
    {
        $validated = $request->validate([
            'setting_key' => 'sometimes|required|string|max:255|unique:system_settings,setting_key,' . $systemSetting->id,
            'setting_value' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['updated_by'] = auth()->id();

        $systemSetting->update($validated);

        return response()->json($systemSetting->load('updater'));
    }

    /**
     * Remove the specified system setting from storage.
     */
    public function destroy(SystemSetting $systemSetting)
    {
        $systemSetting->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk update multiple system settings.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array|min:1',
        ]);

        $updatedUser = auth()->id();

        $updated = DB::transaction(function () use ($validated, $updatedUser) {
            $results = [];

            foreach ($validated['settings'] as $key => $val) {
                if (is_array($val) && isset($val['setting_key'])) {
                    $settingKey = $val['setting_key'];
                    $settingValue = $val['setting_value'] ?? null;
                    $desc = $val['description'] ?? null;
                } else {
                    $settingKey = $key;
                    $settingValue = is_array($val) ? json_encode($val) : (string) $val;
                    $desc = null;
                }

                $recordData = [
                    'setting_value' => $settingValue,
                    'updated_by' => $updatedUser,
                ];

                if ($desc !== null) {
                    $recordData['description'] = $desc;
                }

                $results[] = SystemSetting::updateOrCreate(
                    ['setting_key' => $settingKey],
                    $recordData
                );
            }

            return $results;
        });

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' settings updated successfully.',
            'data' => $updated,
        ]);
    }
}
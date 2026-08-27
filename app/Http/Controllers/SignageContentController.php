<?php

namespace App\Http\Controllers;

use App\Models\SignageContent;
use Illuminate\Http\Request;

class SignageContentController extends Controller
{
    /**
     * Display a listing of signage contents.
     */
    public function index(Request $request)
    {
        $query = SignageContent::withCount('playlistItems');

        if ($request->filled('content_type')) {
            $query->byType($request->content_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'title');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'title', 'content_type', 'duration', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('title', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created signage content in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content_type' => 'nullable',
            'file' => 'required',
            'thumbnail' => 'nullable',
            'duration' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        // Handle case where user uploaded an image file into content_type field
        if ($request->hasFile('content_type')) {
            $uploadedThumb = $request->file('content_type');
            $mime = $uploadedThumb->getMimeType();
            $thumbPath = $uploadedThumb->store('signage/thumbnails', 'public');
            $validated['thumbnail'] = $thumbPath;
            $validated['content_type'] = str_starts_with($mime, 'video/') ? 'video' : 'image';
        }

        // 1. Handle Content File (Physical Upload or URL String)
        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $mime = $uploadedFile->getMimeType();
            $path = $uploadedFile->store('signage/contents', 'public');
            $validated['file'] = $path;

            // Auto-detect content_type if not explicitly provided
            if (empty($validated['content_type'])) {
                if (str_starts_with($mime, 'image/')) {
                    $validated['content_type'] = 'image';
                } elseif (str_starts_with($mime, 'video/')) {
                    $validated['content_type'] = 'video';
                } else {
                    $validated['content_type'] = 'image';
                }
            }
        } elseif (is_string($request->input('file'))) {
            $validated['file'] = $request->input('file');
            if (empty($validated['content_type'])) {
                $ext = strtolower(pathinfo($validated['file'], PATHINFO_EXTENSION));
                $validated['content_type'] = in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm']) ? 'video' : 'image';
            }
        }

        // 2. Handle Thumbnail File
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('signage/thumbnails', 'public');
        } elseif (is_string($request->input('thumbnail'))) {
            $validated['thumbnail'] = $request->input('thumbnail');
        }

        if (empty($validated['content_type'])) {
            $validated['content_type'] = 'image';
        }

        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        if (empty($validated['duration'])) {
            $validated['duration'] = 10;
        }

        $content = SignageContent::create($validated);

        return response()->json($content->loadCount('playlistItems'), 201);
    }

    /**
     * Display the specified signage content.
     */
    public function show(SignageContent $signageContent)
    {
        $latestSchedule = \App\Models\ScreenSchedule::where('signage_content_id', $signageContent->id)->latest()->first();

        $selectedBranchIds = $latestSchedule?->branch_ids ?? [];
        $selectedGroupIds = $latestSchedule?->screen_group_ids ?? [];
        $selectedScreenIds = $latestSchedule?->screen_ids ?? [];

        $selectedBranches = !empty($selectedBranchIds) 
            ? \App\Models\Branch::whereIn('id', $selectedBranchIds)->select('id', 'name')->get() 
            : [];
        $selectedGroups = !empty($selectedGroupIds) 
            ? \App\Models\ScreenGroup::whereIn('id', $selectedGroupIds)->select('id', 'name')->get() 
            : [];
        $selectedScreens = !empty($selectedScreenIds) 
            ? \App\Models\DigitalScreen::whereIn('id', $selectedScreenIds)->select('id', 'screen_name')->get() 
            : [];

        $formattedSchedule = $latestSchedule ? [
            'id' => $latestSchedule->id,
            'schedule_name' => $latestSchedule->schedule_name ?? $latestSchedule->title,
            'title' => $latestSchedule->title,
            'display_type' => $latestSchedule->display_type,
            'start_date' => $latestSchedule->start_date ? (\Carbon\Carbon::parse($latestSchedule->start_date)->format('Y-m-d')) : null,
            'end_date' => $latestSchedule->end_date ? (\Carbon\Carbon::parse($latestSchedule->end_date)->format('Y-m-d')) : null,
            'start_time' => $latestSchedule->start_time,
            'end_time' => $latestSchedule->end_time,
            'recurrence' => $latestSchedule->recurrence,
            'branch_ids' => $selectedBranchIds,
            'branches' => $selectedBranches,
            'screen_group_ids' => $selectedGroupIds,
            'screen_groups' => $selectedGroups,
            'screen_ids' => $selectedScreenIds,
            'screens' => $selectedScreens,
            'priority' => $latestSchedule->priority,
            'status' => $latestSchedule->status,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => [
                'content' => $signageContent,
                'schedule' => $formattedSchedule,
                'form_data' => [
                    'id' => $signageContent->id,
                    'content_name' => $signageContent->content_name ?? $signageContent->title,
                    'title' => $signageContent->title,
                    'content_type' => $signageContent->content_type,
                    'file_url' => $signageContent->file_url,
                    'thumbnail_url' => $signageContent->thumbnail_url,
                    'campaign_tag' => $signageContent->campaign_tag,
                    'description' => $signageContent->description,
                    'resolution' => $signageContent->resolution,
                    'dimensions' => $signageContent->dimensions,
                    'file_size' => $signageContent->file_size,
                    'duration' => $signageContent->duration,
                    'branch_ids' => $selectedBranchIds,
                    'branches' => $selectedBranches,
                    'screen_group_ids' => $selectedGroupIds,
                    'screen_groups' => $selectedGroups,
                    'screen_ids' => $selectedScreenIds,
                    'screens' => $selectedScreens,
                    'display_type' => $latestSchedule?->display_type ?? 'schedule_later',
                    'start_date' => $latestSchedule?->start_date ? (\Carbon\Carbon::parse($latestSchedule->start_date)->format('Y-m-d')) : null,
                    'end_date' => $latestSchedule?->end_date ? (\Carbon\Carbon::parse($latestSchedule->end_date)->format('Y-m-d')) : null,
                    'start_time' => $latestSchedule?->start_time,
                    'end_time' => $latestSchedule?->end_time,
                    'recurrence' => $latestSchedule?->recurrence ?? 'daily',
                    'status' => $signageContent->status,
                ]
            ]
        ]);
    }

    /**
     * Update the specified signage content in storage.
     */
    public function update(Request $request, SignageContent $signageContent)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content_type' => 'nullable|string|max:50',
            'file' => 'nullable',
            'thumbnail' => 'nullable',
            'duration' => 'nullable|integer|min:1',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $mime = $uploadedFile->getMimeType();
            $validated['file'] = $uploadedFile->store('signage/contents', 'public');

            if (empty($validated['content_type'])) {
                $validated['content_type'] = str_starts_with($mime, 'video/') ? 'video' : 'image';
            }
        }

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('signage/thumbnails', 'public');
        }

        $signageContent->update($validated);

        return response()->json($signageContent->loadCount('playlistItems'));
    }

    /**
     * Remove the specified signage content from storage.
     */
    public function destroy(SignageContent $signageContent)
    {
        $signageContent->playlistItems()->delete();
        $signageContent->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle content active / inactive status.
     */
    public function toggleStatus(SignageContent $signageContent)
    {
        $newStatus = $signageContent->status === 'active' ? 'inactive' : 'active';
        $signageContent->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "Signage content status changed to {$newStatus}.",
            'data' => $signageContent->loadCount('playlistItems'),
        ]);
    }

    /**
     * Complete Workflow: Upload Content, Screen Assignment, and Scheduling (Publish / Draft).
     */
    public function publish(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable',
            'content_id' => 'nullable',
            'content_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'content_type' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'file' => 'nullable',
            'thumbnail' => 'nullable',
            'campaign_tag' => 'nullable|string|max:100',
            'tag' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'resolution' => 'nullable|string|max:100',
            'file_size' => 'nullable|string|max:100',
            'dimensions' => 'nullable|string|max:100',
            'aspect_ratio' => 'nullable|string|max:100',
            'duration' => 'nullable|integer|min:1',
            'branch_ids' => 'nullable',
            'branches' => 'nullable',
            'screen_group_ids' => 'nullable',
            'screen_groups' => 'nullable',
            'screen_ids' => 'nullable',
            'screens' => 'nullable',
            'display_type' => 'nullable|string|in:publish_now,schedule_later,now,later',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'recurrence' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,draft,scheduled,inactive',
            'action_type' => 'nullable|string|in:publish,draft,save_draft',
        ]);

        $title = $validated['title'] ?? $validated['content_name'] ?? $validated['name'] ?? 'Signage Promotion #' . rand(100, 999);
        $contentName = $validated['content_name'] ?? $title;
        $campaignTag = $validated['campaign_tag'] ?? $validated['tag'] ?? 'General Offer';
        $description = $validated['description'] ?? '';
        $rawType = strtolower($validated['content_type'] ?? $validated['type'] ?? 'image');
        $contentType = in_array($rawType, ['video', 'playlist']) ? $rawType : 'image';

        // 1. Handle File Upload or Asset URL
        $filePath = null;
        $fileSize = $validated['file_size'] ?? null;
        $fileName = null;

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $fileName = $uploadedFile->getClientOriginalName();
            $fileSize = round($uploadedFile->getSize() / (1024 * 1024), 1) . ' MB';
            $mime = $uploadedFile->getMimeType();
            $filePath = $uploadedFile->store('signage/contents', 'public');

            if (empty($validated['content_type'])) {
                $contentType = str_starts_with($mime, 'video/') ? 'video' : 'image';
            }
        } elseif ($request->filled('file')) {
            $filePath = $request->input('file');
            $fileName = basename($filePath);
        }

        // Thumbnail
        $thumbPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbPath = $request->file('thumbnail')->store('signage/thumbnails', 'public');
        }

        $resolution = $validated['resolution'] ?? '1920 x 1080 16:9';
        $dimensions = $validated['dimensions'] ?? '16:9';
        $aspectRatio = $validated['aspect_ratio'] ?? '16:9';
        $duration = (int) ($validated['duration'] ?? 15);

        // Status Handling
        $actionType = $validated['action_type'] ?? null;
        $status = ($actionType === 'draft' || $actionType === 'save_draft' || ($validated['status'] ?? '') === 'draft')
            ? 'draft'
            : 'active';

        // Upsert Content Record
        $contentId = $validated['id'] ?? $validated['content_id'] ?? null;
        $contentData = [
            'title' => $title,
            'content_name' => $contentName,
            'content_type' => $contentType,
            'campaign_tag' => $campaignTag,
            'description' => $description,
            'resolution' => $resolution,
            'file_size' => $fileSize,
            'dimensions' => $dimensions,
            'aspect_ratio' => $aspectRatio,
            'duration' => $duration,
            'status' => $status,
        ];

        if ($filePath) {
            $contentData['file'] = $filePath;
        }
        if ($thumbPath) {
            $contentData['thumbnail'] = $thumbPath;
        }

        if ($contentId && $existing = SignageContent::find($contentId)) {
            $existing->update($contentData);
            $content = $existing;
        } else {
            if (empty($contentData['file'])) {
                $contentData['file'] = 'signage/contents/default_promo.jpg';
            }
            $content = SignageContent::create($contentData);
        }

        // 2. Parse Branches & Screen Groups Selection
        $branchIds = is_array($validated['branch_ids'] ?? null) 
            ? $validated['branch_ids'] 
            : (json_decode($validated['branch_ids'] ?? '[]', true) ?: []);

        $screenGroupIds = is_array($validated['screen_group_ids'] ?? null) 
            ? $validated['screen_group_ids'] 
            : (json_decode($validated['screen_group_ids'] ?? '[]', true) ?: []);

        $screenIds = is_array($validated['screen_ids'] ?? null) 
            ? $validated['screen_ids'] 
            : (json_decode($validated['screen_ids'] ?? '[]', true) ?: []);

        // 3. Create or Update Schedule
        $displayType = in_array(strtolower($validated['display_type'] ?? ''), ['now', 'publish_now']) ? 'publish_now' : 'schedule_later';
        $startDate = !empty($validated['start_date']) ? \Carbon\Carbon::parse($validated['start_date'])->format('Y-m-d') : now()->format('Y-m-d');
        $endDate = !empty($validated['end_date']) ? \Carbon\Carbon::parse($validated['end_date'])->format('Y-m-d') : now()->addDays(30)->format('Y-m-d');
        $startTime = !empty($validated['start_time']) ? \Carbon\Carbon::parse($validated['start_time'])->format('H:i:s') : '10:00:00';
        $endTime = !empty($validated['end_time']) ? \Carbon\Carbon::parse($validated['end_time'])->format('H:i:s') : '23:59:00';
        $recurrence = $validated['recurrence'] ?? 'daily';

        $schedule = \App\Models\ScreenSchedule::create([
            'signage_content_id' => $content->id,
            'schedule_name' => $validated['schedule_name'] ?? $title,
            'title' => $title,
            'display_type' => $displayType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'recurrence' => $recurrence,
            'branch_ids' => $branchIds,
            'screen_group_ids' => $screenGroupIds,
            'screen_ids' => $screenIds,
            'status' => $status,
        ]);

        // Calculate Target Screen Count
        if (!empty($screenIds)) {
            $targetScreensCount = count($screenIds);
        } elseif (!empty($screenGroupIds)) {
            $targetScreensCount = \App\Models\DigitalScreen::whereIn('screen_group_id', $screenGroupIds)->count();
        } else {
            $totalInDb = \App\Models\DigitalScreen::count();
            $targetScreensCount = $totalInDb > 0 ? $totalInDb : 18;
        }

        $selectedBranches = !empty($branchIds) 
            ? \App\Models\Branch::whereIn('id', $branchIds)->select('id', 'name')->get() 
            : [];
        $selectedGroups = !empty($screenGroupIds) 
            ? \App\Models\ScreenGroup::whereIn('id', $screenGroupIds)->select('id', 'name')->get() 
            : [];
        $selectedScreens = !empty($screenIds) 
            ? \App\Models\DigitalScreen::whereIn('id', $screenIds)->select('id', 'screen_name')->get() 
            : [];

        $formattedSchedule = [
            'id' => $schedule->id,
            'schedule_name' => $schedule->schedule_name ?? $schedule->title,
            'title' => $schedule->title,
            'display_type' => $displayType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'recurrence' => $recurrence,
            'branch_ids' => $branchIds,
            'branches' => $selectedBranches,
            'screen_group_ids' => $screenGroupIds,
            'screen_groups' => $selectedGroups,
            'screen_ids' => $screenIds,
            'screens' => $selectedScreens,
            'status' => $status,
        ];

        $selectedBranchesCount = !empty($branchIds) ? count($branchIds) : 0;

        return response()->json([
            'success' => true,
            'message' => $status === 'draft' ? 'Signage content saved as draft successfully.' : 'Signage content published and scheduled across screens successfully.',
            'content' => $content,
            'schedule' => $formattedSchedule,
            'review_and_publish_summary' => [
                'content_title' => $title,
                'content_type' => ucfirst($contentType),
                'branches' => "{$selectedBranchesCount} Selected",
                'screens' => count($screenGroupIds) > 0 ? count($screenGroupIds) . " Groups ({$targetScreensCount} Screens)" : "{$targetScreensCount} Screens",
                'schedule_window' => \Carbon\Carbon::parse($startDate)->format('j M, Y') . ' - ' . \Carbon\Carbon::parse($endDate)->format('j M, Y'),
                'status_badge' => $status === 'draft' ? 'Draft' : 'Ready To Publish',
                'preview' => [
                    'file_name' => $fileName ?? 'promo_banner.jpg',
                    'file_size' => $fileSize ?? '2.4 MB',
                    'resolution' => $resolution,
                    'dimensions' => $dimensions,
                    'file_url' => $content->file_url,
                ]
            ],
        ], 201);
    }
}
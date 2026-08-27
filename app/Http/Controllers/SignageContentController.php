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
        return response()->json($signageContent->loadCount('playlistItems'));
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
}
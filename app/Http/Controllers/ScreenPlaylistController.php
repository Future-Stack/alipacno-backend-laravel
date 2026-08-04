<?php

namespace App\Http\Controllers;

use App\Models\ScreenPlaylist;
use App\Models\ScreenPlaylistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScreenPlaylistController extends Controller
{
    /**
     * Display a listing of screen playlists.
     */
    public function index(Request $request)
    {
        $query = ScreenPlaylist::with(['creator', 'items.content'])->withCount('items');

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'title');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'title', 'created_at'];

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
     * Store a newly created screen playlist in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'created_by' => 'nullable|exists:users,id',
            'items' => 'nullable|array',
            'items.*.signage_content_id' => 'required_with:items|exists:signage_contents,id',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        if (empty($validated['created_by']) && $request->user()) {
            $validated['created_by'] = $request->user()->id;
        }

        $playlist = DB::transaction(function () use ($validated) {
            $playlist = ScreenPlaylist::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'created_by' => $validated['created_by'] ?? null,
            ]);

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $index => $itemData) {
                    $playlist->items()->create([
                        'signage_content_id' => $itemData['signage_content_id'],
                        'sort_order' => $itemData['sort_order'] ?? ($index + 1),
                    ]);
                }
            }

            return $playlist;
        });

        return response()->json($playlist->load(['creator', 'items.content'])->loadCount('items'), 201);
    }

    /**
     * Display the specified screen playlist.
     */
    public function show(ScreenPlaylist $screenPlaylist)
    {
        return response()->json($screenPlaylist->load(['creator', 'items.content'])->loadCount('items'));
    }

    /**
     * Update the specified screen playlist in storage.
     */
    public function update(Request $request, ScreenPlaylist $screenPlaylist)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.signage_content_id' => 'required_with:items|exists:signage_contents,id',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $validated, $screenPlaylist) {
            $screenPlaylist->update(array_intersect_key($validated, array_flip(['title', 'description'])));

            if ($request->has('items')) {
                $screenPlaylist->items()->delete();
                if (!empty($validated['items'])) {
                    foreach ($validated['items'] as $index => $itemData) {
                        $screenPlaylist->items()->create([
                            'signage_content_id' => $itemData['signage_content_id'],
                            'sort_order' => $itemData['sort_order'] ?? ($index + 1),
                        ]);
                    }
                }
            }
        });

        return response()->json($screenPlaylist->load(['creator', 'items.content'])->loadCount('items'));
    }

    /**
     * Remove the specified screen playlist from storage.
     */
    public function destroy(ScreenPlaylist $screenPlaylist)
    {
        DB::transaction(function () use ($screenPlaylist) {
            $screenPlaylist->items()->delete();
            $screenPlaylist->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Sync and re-order playlist items.
     */
    public function syncItems(Request $request, ScreenPlaylist $screenPlaylist)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.signage_content_id' => 'required|exists:signage_contents,id',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $screenPlaylist) {
            $screenPlaylist->items()->delete();

            foreach ($validated['items'] as $index => $itemData) {
                $screenPlaylist->items()->create([
                    'signage_content_id' => $itemData['signage_content_id'],
                    'sort_order' => $itemData['sort_order'] ?? ($index + 1),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Playlist items synchronized successfully.',
            'data' => $screenPlaylist->load(['creator', 'items.content'])->loadCount('items'),
        ]);
    }

    /**
     * Append a single signage content item to playlist.
     */
    public function addItem(Request $request, ScreenPlaylist $screenPlaylist)
    {
        $validated = $request->validate([
            'signage_content_id' => 'required|exists:signage_contents,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $maxSort = $screenPlaylist->items()->max('sort_order') ?? 0;

        $item = $screenPlaylist->items()->create([
            'signage_content_id' => $validated['signage_content_id'],
            'sort_order' => $validated['sort_order'] ?? ($maxSort + 1),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item added to playlist successfully.',
            'data' => $screenPlaylist->load(['creator', 'items.content'])->loadCount('items'),
        ], 201);
    }
}
<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    /**
     * List all pages.
     */
    public function index()
    {
        try {

            $pages = Page::latest()->get();

            return response()->json([
                'success' => true,
                'data' => $pages,
            ], 200);

        } catch (\Exception $e) {

            Log::error('Page Index Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pages.',
            ], 500);
        }
    }

    /**
     * Store page.
     */
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'title'   => 'required|string|max:255',
                'content' => 'required|array',
                'status'  => 'nullable|boolean',
            ], [
                'title.required'   => 'Title is required.',
                'content.required' => 'Content is required.',
                'content.array'    => 'Content must be a valid JSON object.',
            ]);

            $validated['slug'] = $this->generateSlug($validated['title']);
            $validated['status'] = $validated['status'] ?? true;

            $page = Page::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Page created successfully.',
                'data' => $page,
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);

        } catch (\Exception $e) {

            Log::error('Page Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create page.',
            ], 500);
        }
    }

    /**
     * Show single page.
     */
    public function show($page_id)
    {
        try {

            $page = Page::findOrFail($page_id);

            return response()->json([
                'success' => true,
                'data' => $page,
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Page not found.',
            ], 404);

        } catch (\Exception $e) {

            Log::error('Page Show Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch page.',
            ], 500);
        }
    }

    /**
     * Update page.
     */
    public function update(Request $request, $page_id)
    {
        try {

            $validated = $request->validate([
                'title'   => 'sometimes|string|max:255',
                'content' => 'sometimes|array',
                'status'  => 'nullable|boolean',
            ], [
                'content.array' => 'Content must be a valid JSON object.',
            ]);

            $page = Page::findOrFail($page_id);

            if (isset($validated['title'])) {
                $validated['slug'] = $this->generateSlug(
                    $validated['title'],
                    $page->id
                );
            }

            $page->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Page updated successfully.',
                'data' => $page->fresh(),
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Page not found.',
            ], 404);

        } catch (\Exception $e) {

            Log::error('Page Update Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update page.',
            ], 500);
        }
    }

    /**
     * Delete page.
     */
    public function destroy($page_id)
    {
        try {

            $page = Page::findOrFail($page_id);

            $page->delete();

            return response()->json([
                'success' => true,
                'message' => 'Page deleted successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Page not found.',
            ], 404);

        } catch (\Exception $e) {

            Log::error('Page Delete Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete page.',
            ], 500);
        }
    }

    /**
     * Generate unique slug.
     */
    private function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);

        $query = Page::where('slug', 'LIKE', $slug . '%');

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $count = $query->count();

        return $count > 0 ? $slug . '-' . ($count + 1) : $slug;
    }
}
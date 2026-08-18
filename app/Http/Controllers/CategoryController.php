<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request)
    {
        $query = Category::withCount('menuItems');

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by category/parent category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by category name
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(
                'name',
                'like',
                '%' . $search . '%'
            );
        }

        // Sorting
        $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        // Get all categories
        if ($request->boolean('all')) {
            $categories = $query->get();

            return response()->json([
                'data' => $this->formatCategories($categories),
            ]);
        }

        // Paginated categories
        $categories = $query->paginate(
            $request->input('per_page', 50)
        );

        // Format image URLs
        $categories->getCollection()->transform(function ($category) {
            return $this->formatCategory($category);
        });

        return response()->json($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => [
                'nullable',
                'exists:restaurants,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'icon' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:2048',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'sort_order' => [
                'nullable',
                'integer',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        // Generate slug
        $validated['slug'] = Str::slug($validated['name']);

        // Upload icon
        if ($request->hasFile('icon')) {
            $validated['icon'] = $request
                ->file('icon')
                ->store('categories/icons', 'public');
        }

        // Upload category image
        if ($request->hasFile('image')) {
            $validated['image'] = $request
                ->file('image')
                ->store('categories/images', 'public');
        }

        // Create category
        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $this->formatCategory($category),
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        $category->load('menuItems.sizes');

        return response()->json([
            'data' => $this->formatCategory($category),
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'restaurant_id' => [
                'nullable',
                'exists:restaurants,id',
            ],

            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'icon' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:2048',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'sort_order' => [
                'nullable',
                'integer',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        // Update slug when name changes
        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Replace old icon with new icon
        if ($request->hasFile('icon')) {

            if ($category->icon) {
                Storage::disk('public')->delete($category->icon);
            }

            $validated['icon'] = $request
                ->file('icon')
                ->store('categories/icons', 'public');
        }

        // Replace old image with new image
        if ($request->hasFile('image')) {

            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $validated['image'] = $request
                ->file('image')
                ->store('categories/images', 'public');
        }

        // Update category
        $category->update($validated);

        $category->refresh();

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $this->formatCategory($category),
        ]);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category)
    {
        // Delete icon
        if ($category->icon) {
            Storage::disk('public')->delete($category->icon);
        }

        // Delete image
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        // Delete category
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }

    /**
     * Format category with full asset URLs.
     */
    private function formatCategory(Category $category): Category
    {
        $category->icon_url = $category->icon
            ? asset('storage/' . $category->icon)
            : null;

        $category->image_url = $category->image
            ? asset('storage/' . $category->image)
            : null;

        return $category;
    }

    /**
     * Format multiple categories.
     */
    private function formatCategories($categories)
    {
        return $categories->map(function ($category) {
            return $this->formatCategory($category);
        });
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews.
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'restaurant', 'menuItem', 'order']);

        if ($request->filled('restaurant_id')) {
            $query->forRestaurant($request->restaurant_id);
        }

        if ($request->filled('menu_item_id')) {
            $query->forMenuItem($request->menu_item_id);
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('rating')) {
            $query->byRating($request->rating);
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (int)$request->min_rating);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'rating', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'restaurant_id' => 'required|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'order_id' => 'nullable|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['user_id']) && $request->user()) {
            $validated['user_id'] = $request->user()->id;
        }

        if (!empty($validated['order_id'])) {
            $existing = Review::where('order_id', $validated['order_id'])
                ->where('user_id', $validated['user_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'You have already submitted a review for this order.',
                ], 422);
            }
        }

        $review = Review::create($validated);

        if (!empty($review->menu_item_id)) {
            $this->recalculateMenuItemRating($review->menu_item_id);
        }

        return response()->json($review->load(['user', 'restaurant', 'menuItem', 'order']), 201);
    }

    /**
     * Display the specified review.
     */
    public function show(Review $review)
    {
        return response()->json($review->load(['user', 'restaurant', 'menuItem', 'order']));
    }

    /**
     * Update the specified review in storage.
     */
    public function update(Request $request, Review $review)
    {
        $validated = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        $review->update($validated);

        if (!empty($review->menu_item_id)) {
            $this->recalculateMenuItemRating($review->menu_item_id);
        }

        return response()->json($review->load(['user', 'restaurant', 'menuItem', 'order']));
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy(Review $review)
    {
        $menuItemId = $review->menu_item_id;
        $review->delete();

        if ($menuItemId) {
            $this->recalculateMenuItemRating($menuItemId);
        }

        return response()->json(null, 204);
    }

    /**
     * Get review summary / distribution rating stats.
     */
    public function summary(Request $request)
    {
        $query = Review::query();

        if ($request->filled('restaurant_id')) {
            $query->forRestaurant($request->restaurant_id);
        }

        if ($request->filled('menu_item_id')) {
            $query->forMenuItem($request->menu_item_id);
        }

        $totalReviews = (clone $query)->count();
        $averageRating = (clone $query)->avg('rating') ?? 0;

        $starCounts = [
            '5_star' => (clone $query)->where('rating', 5)->count(),
            '4_star' => (clone $query)->where('rating', 4)->count(),
            '3_star' => (clone $query)->where('rating', 3)->count(),
            '2_star' => (clone $query)->where('rating', 2)->count(),
            '1_star' => (clone $query)->where('rating', 1)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total_reviews' => $totalReviews,
                'average_rating' => round($averageRating, 2),
                'breakdown' => $starCounts,
            ],
        ]);
    }

    /**
     * Recalculate average rating and review count for a menu item.
     */
    protected function recalculateMenuItemRating(?int $menuItemId): void
    {
        if (!$menuItemId) {
            return;
        }

        $menuItem = MenuItem::find($menuItemId);
        if (!$menuItem) {
            return;
        }

        $avgRating = Review::where('menu_item_id', $menuItemId)->avg('rating') ?? 0;
        $count = Review::where('menu_item_id', $menuItemId)->count();

        $menuItem->update([
            'rating' => round($avgRating, 2),
            'review_count' => $count,
        ]);
    }
}
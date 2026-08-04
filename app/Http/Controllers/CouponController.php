<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Display a listing of coupons.
     */
    public function index(Request $request)
    {
        $query = Coupon::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = strtoupper(trim($request->search));
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->boolean('active_only')) {
            $query->where('status', 'active')
                  ->where(function ($q) {
                      $q->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>=', now()->toDateString());
                  });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created coupon.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'code' => 'required|string|max:50|unique:coupons,code',
            'discount_type' => 'required|in:percentage,fixed',
            'discount' => 'required|numeric|min:0.01',
            'minimum_order' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'status' => 'nullable|in:active,inactive,expired',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        if (!isset($validated['restaurant_id'])) {
            $validated['restaurant_id'] = 1;
        }

        $coupon = Coupon::create($validated);

        return response()->json($coupon, 201);
    }

    /**
     * Display the specified coupon.
     */
    public function show(Coupon $coupon)
    {
        return response()->json($coupon);
    }

    /**
     * Update the specified coupon in storage.
     */
    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'restaurant_id' => 'sometimes|exists:restaurants,id',
            'code' => 'sometimes|string|max:50|unique:coupons,code,' . $coupon->id,
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount' => 'sometimes|numeric|min:0.01',
            'minimum_order' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'status' => 'sometimes|in:active,inactive,expired',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $coupon->update($validated);

        return response()->json($coupon);
    }

    /**
     * Remove the specified coupon from storage.
     */
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json(null, 204);
    }

    /**
     * Apply/Verify a coupon code against a cart total.
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'cart_total' => 'required|numeric|min:0',
        ]);

        $code = strtoupper(trim($validated['code']));
        $cartTotal = (float) $validated['cart_total'];

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid promo coupon code.',
            ], 404);
        }

        if ($coupon->status !== 'active') {
            return response()->json([
                'valid' => false,
                'message' => 'This coupon is no longer active.',
            ], 422);
        }

        if ($coupon->expiry_date && $coupon->expiry_date < now()->toDateString()) {
            $coupon->update(['status' => 'expired']);
            return response()->json([
                'valid' => false,
                'message' => 'This coupon has expired.',
            ], 422);
        }

        if ($coupon->minimum_order > 0 && $cartTotal < $coupon->minimum_order) {
            return response()->json([
                'valid' => false,
                'message' => 'Minimum order amount of £' . number_format($coupon->minimum_order, 2) . ' required to use this coupon.',
            ], 422);
        }

        $discountAmount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discountAmount = ($cartTotal * $coupon->discount) / 100;
        } else {
            $discountAmount = min($cartTotal, $coupon->discount);
        }

        $newTotal = max(0, $cartTotal - $discountAmount);

        return response()->json([
            'valid' => true,
            'message' => 'Coupon applied successfully!',
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount,
            ],
            'discount_amount' => round($discountAmount, 2),
            'new_total' => round($newTotal, 2),
        ]);
    }
}
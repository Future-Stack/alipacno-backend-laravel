<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyPoint;
use Illuminate\Http\Request;

class LoyaltyPointController extends Controller
{
    /**
     * Display a listing of loyalty points transactions.
     */
    public function index(Request $request)
    {
        $userId = $request->input('user_id', $request->user()?->id);

        $query = LoyaltyPoint::with(['user', 'order']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created loyalty point transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'points' => 'required|integer|min:1',
            'type' => 'required|in:earn,redeem,expire',
            'remarks' => 'nullable|string|max:255',
        ]);

        if (empty($validated['user_id']) && $request->user()) {
            $validated['user_id'] = $request->user()->id;
        }

        $loyaltyPoint = LoyaltyPoint::create($validated);

        return response()->json($loyaltyPoint->load(['user', 'order']), 201);
    }

    /**
     * Display the specified loyalty point transaction.
     */
    public function show(LoyaltyPoint $loyaltyPoint)
    {
        return response()->json($loyaltyPoint->load(['user', 'order']));
    }

    /**
     * Update the specified loyalty point transaction.
     */
    public function update(Request $request, LoyaltyPoint $loyaltyPoint)
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:255',
        ]);

        $loyaltyPoint->update($validated);

        return response()->json($loyaltyPoint->load(['user', 'order']));
    }

    /**
     * Remove the specified loyalty point transaction.
     */
    public function destroy(LoyaltyPoint $loyaltyPoint)
    {
        $loyaltyPoint->delete();

        return response()->json(null, 204);
    }

    /**
     * Get user loyalty point balance.
     */
    public function userBalance(Request $request)
    {
        $userId = $request->input('user_id', $request->user()?->id);

        if (!$userId) {
            return response()->json(['message' => 'User ID is required to fetch loyalty balance.'], 422);
        }

        $earned = LoyaltyPoint::where('user_id', $userId)->where('type', 'earn')->sum('points');
        $redeemed = LoyaltyPoint::where('user_id', $userId)->where('type', 'redeem')->sum('points');
        $expired = LoyaltyPoint::where('user_id', $userId)->where('type', 'expire')->sum('points');

        $balance = max(0, $earned - ($redeemed + $expired));

        return response()->json([
            'user_id' => (int) $userId,
            'current_balance' => $balance,
            'total_earned' => (int) $earned,
            'total_redeemed' => (int) $redeemed,
            'total_expired' => (int) $expired,
        ]);
    }
}
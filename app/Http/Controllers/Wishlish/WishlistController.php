<?php

namespace App\Http\Controllers\Wishlish;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    // 🟢 Create
    public function toggle(Request $request)
    {
        try {
            $validated = $request->validate([
                'menu_item_id' => 'required|exists:menu_items,id',
            ]);

            $userId = Auth::id();

            // Check if wishlist item already exists
            $wishlist = Wishlist::where('menu_item_id', $validated['menu_item_id'])
                ->where('user_id', $userId)
                ->first();

            if ($wishlist) {
                // Delete if exists
                $wishlist->delete();

                return response()->json([
                    'success' => true,
                    'action' => 'deleted',
                    'message' => 'Wishlist item removed successfully.'
                ]);
            } else {
                // Create if not exists
                $wishlist = Wishlist::create([
                    'menu_item_id' => $validated['menu_item_id'],
                    'user_id' => $userId,
                ]);

                return response()->json([
                    'success' => true,
                    'action' => 'created',
                    'data' => $wishlist,
                    'message' => 'Wishlist item added successfully.'
                ], 201);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist toggle failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Fetch wishlist items for the logged-in user
    public function myWishlist()
    {
        try {
            $userId = Auth::id();

            $wishlists = Wishlist::with('menuItem')
                ->where('user_id', $userId)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $wishlists,
                'message' => 'Wishlist fetched successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wishlist.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

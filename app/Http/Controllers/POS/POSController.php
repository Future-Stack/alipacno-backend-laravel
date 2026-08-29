<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ItemSize;
use App\Models\MenuItem;
use App\Models\Topping;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class POSController extends Controller
{
    public function getCategoryList()
    {
        try {
            $categories = Category::where('is_active', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Category List fetched successfully',
                'data' => $categories,

            ]);
        } catch (Exception $exception) {

            Log::error('category list fetching error: ' . $exception->getMessage());
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function getMenuItem(string $categoryID, string $branchID, Request $request)
    {
        try {
            $query = MenuItem::with(['category', 'sizes', 'cookingPreferences', 'spiceLevels', 'toppings', 'recipes.ingredients.inventoryItem'])
                ->where('category_id', $categoryID)
                ->where('branch_id', $branchID);

            if ($request->has('is_popular')) {
                $query->where('is_popular', $request->query('is_popular'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Menu item fetched successfully',
                'data' => $query->get(),
            ]);
        } catch (Exception $exception) {
            Log::error('Menuitem list fetching error: ' . $exception->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',

            ]);
        }
    }

    public function showMenuItem(string $menuItem)
    {
        try {
            $menuItem = MenuItem::where('id', $menuItem)->first();

            $data = $menuItem->load(['category', 'sizes', 'cookingPreferences', 'spiceLevels', 'toppings', 'reviews', 'recipes.ingredients.inventoryItem']);

            return response()->json([
                'success' => true,
                'message' => 'Menu item fetched successfully',
                'data' => $data,
            ]);
        }
        catch (Exception $exception) {
            Log::error('show menu item fetching error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ]);
        }
    }

    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'size_id' => 'nullable|exists:item_sizes,id',
            'cooking_preference_id' => 'nullable|exists:cooking_preferences,id',
            'spice_level_id' => 'nullable|exists:spice_levels,id',
            'quantity' => 'required|integer|min:1',
            'special_instructions' => 'nullable|string',
            'toppings' => 'nullable|array',
            'toppings.*' => 'exists:toppings,id',
        ]);

        $cartID = auth()->id();

        $quantity = $validated['quantity'] ?? 1;
        $menuItem = MenuItem::findOrFail($validated['menu_item_id']);

        $unitPrice = $menuItem->price;
        if (!empty($validated['size_id'])) {
            $size = ItemSize::find($validated['size_id']);
            if ($size) {
                $unitPrice += $size->extra_price;
            }
        }

        // Add toppings price if applicable
        if (!empty($validated['toppings'])) {
            $toppings = Topping::whereIn('id', $validated['toppings'])->get();
            foreach ($toppings as $topping) {
                $unitPrice += $topping->price;
            }
        }

        // Check for existing identical cart item
        $existingItem = CartItem::where('cart_id', $cartID)
            ->where('menu_item_id', $validated['menu_item_id'])
            ->where('size_id', $validated['size_id'] ?? null)
            ->where('cooking_preference_id', $validated['cooking_preference_id'] ?? null)
            ->where('spice_level_id', $validated['spice_level_id'] ?? null)
            ->first();

        if ($existingItem) {
            $newQty = $existingItem->quantity + $quantity;
            $existingItem->update([
                'quantity' => $newQty,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $newQty,
            ]);
            $cartItem = $existingItem;
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cartID,
                'menu_item_id' => $validated['menu_item_id'],
                'size_id' => $validated['size_id'] ?? null,
                'cooking_preference_id' => $validated['cooking_preference_id'] ?? null,
                'spice_level_id' => $validated['spice_level_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $quantity,
                'special_instructions' => $validated['special_instructions'] ?? null,
            ]);
        }

        // Attach toppings
        if (!empty($validated['toppings'])) {
            $cartItem->toppings()->delete();
            foreach ($validated['toppings'] as $toppingId) {
                $topping = Topping::find($toppingId);
                if ($topping) {
                    $cartItem->toppings()->create([
                        'topping_id' => $topping->id,
                        'price' => $topping->price,
                    ]);
                }
            }
        }

        return response()->json($cartItem->load(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping']), 201);
    }
}

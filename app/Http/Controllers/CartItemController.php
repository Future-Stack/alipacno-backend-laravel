<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\ItemSize;
use App\Models\Topping;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    /**
     * Display a listing of cart items.
     */
    public function index(Request $request)
    {
        $query = CartItem::with(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping']);

        if ($request->filled('cart_id')) {
            $query->where('cart_id', $request->cart_id);
        }

        return response()->json($query->get());
    }

    /**
     * Add item to cart.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cart_id' => 'required|exists:carts,id',
            'menu_item_id' => 'required|exists:menu_items,id',
            'size_id' => 'nullable|exists:item_sizes,id',
            'cooking_preference_id' => 'nullable|exists:cooking_preferences,id',
            'spice_level_id' => 'nullable|exists:spice_levels,id',
            'quantity' => 'nullable|integer|min:1',
            'special_instructions' => 'nullable|string',
            'toppings' => 'nullable|array',
            'toppings.*' => 'exists:toppings,id',
        ]);

        $quantity = $validated['quantity'] ?? 1;
        $menuItem = MenuItem::findOrFail($validated['menu_item_id']);
        $cart = Cart::find($validated['cart_id']);

        // Check for existing identical cart item
        $existingItem = CartItem::where('cart_id', $validated['cart_id'])
            ->where('menu_item_id', $validated['menu_item_id'])
            ->where('size_id', $validated['size_id'] ?? null)
            ->where('cooking_preference_id', $validated['cooking_preference_id'] ?? null)
            ->where('spice_level_id', $validated['spice_level_id'] ?? null)
            ->first();

        $targetQuantity = $existingItem ? ($existingItem->quantity + $quantity) : $quantity;

        // Branch-wise stock validation if cart is scoped to a branch
        $branchId = $cart?->branch_id ?? $request->input('branch_id');
        if (!empty($branchId)) {
            InventoryStockService::validateStockForItems((int) $branchId, [
                ['menu_item_id' => $validated['menu_item_id'], 'quantity' => $targetQuantity]
            ]);
        }

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
                'cart_id' => $validated['cart_id'],
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

    /**
     * Display specified cart item.
     */
    public function show(CartItem $cartItem)
    {
        return response()->json($cartItem->load(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping']));
    }

    /**
     * Update specified cart item (quantity, instructions).
     */
    public function update(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|integer',
            'special_instructions' => 'nullable|string',
        ]);

        if (isset($validated['quantity'])) {
            if ($validated['quantity'] <= 0) {
                $cartItem->delete();
                return response()->json(null, 204);
            }

            // Validate stock if cart has a branch
            $branchId = $cartItem->cart?->branch_id ?? $request->input('branch_id');
            if (!empty($branchId)) {
                InventoryStockService::validateStockForItems((int) $branchId, [
                    ['menu_item_id' => $cartItem->menu_item_id, 'quantity' => (int) $validated['quantity']]
                ]);
            }

            $validated['total_price'] = $cartItem->unit_price * $validated['quantity'];
        }

        $cartItem->update($validated);

        return response()->json($cartItem->load(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping']));
    }

    /**
     * Remove item from cart.
     */
    public function destroy(CartItem $cartItem)
    {
        $cartItem->delete();

        return response()->json(null, 204);
    }
}

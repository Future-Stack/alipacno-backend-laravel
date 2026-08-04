<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    /**
     * Get or create active shopping cart for authenticated user or session ID.
     */
    public function index(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $this->recalculateCart($cart);

        return response()->json($cart->load([
            'items.menuItem',
            'items.size',
            'items.cookingPreference',
            'items.spiceLevel',
            'items.toppings.topping',
        ]));
    }

    /**
     * Create/Update shopping cart.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_type' => 'nullable|in:delivery,collection,dine_in,table,table_order',
            'delivery_postcode' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'tip' => 'nullable|numeric|min:0',
        ]);

        $cart = $this->getOrCreateCart($request);

        $cart->update(array_filter($validated, fn($v) => !is_null($v)));
        $this->recalculateCart($cart);

        return response()->json($cart->load([
            'items.menuItem',
            'items.size',
            'items.cookingPreference',
            'items.spiceLevel',
            'items.toppings.topping',
        ]));
    }

    /**
     * Display the specified cart.
     */
    public function show(Cart $cart)
    {
        $this->recalculateCart($cart);

        return response()->json($cart->load([
            'items.menuItem',
            'items.size',
            'items.cookingPreference',
            'items.spiceLevel',
            'items.toppings.topping',
        ]));
    }

    /**
     * Update the specified cart.
     */
    public function update(Request $request, Cart $cart)
    {
        $validated = $request->validate([
            'order_type' => 'sometimes|in:delivery,collection,dine_in,table,table_order',
            'delivery_postcode' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'tip' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $cart->update($validated);
        $this->recalculateCart($cart);

        return response()->json($cart->load([
            'items.menuItem',
            'items.size',
            'items.cookingPreference',
            'items.spiceLevel',
            'items.toppings.topping',
        ]));
    }

    /**
     * Clear all items in cart.
     */
    public function destroy(Cart $cart)
    {
        $cart->items()->delete();
        $cart->update([
            'subtotal' => 0,
            'vat' => 0,
            'delivery_fee' => 0,
            'discount' => 0,
            'tip' => 0,
            'total' => 0,
            'loyalty_points' => 0,
        ]);

        return response()->json(['message' => 'Cart cleared successfully']);
    }

    /**
     * Helper to get or create active cart.
     */
    protected function getOrCreateCart(Request $request): Cart
    {
        $userId = $request->user()?->id;
        $sessionId = $request->header('X-Session-ID') ?: $request->input('session_id');

        if ($userId) {
            $cart = Cart::where('user_id', $userId)->latest()->first();
            if (!$cart) {
                $cart = Cart::create(['user_id' => $userId]);
            }
        } elseif ($sessionId) {
            $cart = Cart::where('session_id', $sessionId)->latest()->first();
            if (!$cart) {
                $cart = Cart::create(['session_id' => $sessionId]);
            }
        } else {
            $sessionId = Str::uuid()->toString();
            $cart = Cart::create(['session_id' => $sessionId]);
        }

        return $cart;
    }

    /**
     * Recalculate cart subtotal, VAT, fees, and total.
     */
    protected function recalculateCart(Cart $cart): void
    {
        $subtotal = 0;

        foreach ($cart->items as $item) {
            $itemTotal = $item->unit_price * $item->quantity;
            if ($item->toppings) {
                foreach ($item->toppings as $top) {
                    $itemTotal += ($top->price * $item->quantity);
                }
            }
            $item->update(['total_price' => $itemTotal]);
            $subtotal += $itemTotal;
        }

        $vat = $subtotal > 0 ? 2.00 : 0.00;
        $deliveryFee = ($cart->order_type === 'delivery' && $subtotal > 0) ? 0.00 : 0.00;
        $total = max(0, $subtotal + $vat + $deliveryFee + $cart->tip - $cart->discount);
        $loyaltyPoints = (int) floor($subtotal / 10) * 5;

        $cart->update([
            'subtotal' => $subtotal,
            'vat' => $vat,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'loyalty_points' => $loyaltyPoints,
        ]);
    }
}
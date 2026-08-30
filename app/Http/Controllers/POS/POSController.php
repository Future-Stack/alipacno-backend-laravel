<?php

namespace App\Http\Controllers\POS;

use App\Events\NewDeliveryBroadcastEvent;
use App\Events\OrderStatusUpdatedBroadcastEvent;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ItemSize;
use App\Models\KitchenOrder;
use App\Models\KitchenStation;
use App\Models\LoyaltyPoint;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Topping;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\FirebaseNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeClient;


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
        } catch (Exception $exception) {
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

        $adminID = auth()->id();

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
        $existingItem = CartItem::where('admin_id', $adminID)
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
                'admin_id' => $adminID,
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

    public function getCartItems()
    {
        try {
            $query = CartItem::with(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping'])
                ->where('admin_id', auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Cart item fetched successfully',
                'data' => $query->get(),
            ]);

        } catch (Exception $exception) {
            Log::error('show cart item fetching error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ]);
        }
    }

    public function updateCartItem(Request $request, string $cartItemId)
    {
        try {
            $validated = $request->validate([
                'quantity' => 'sometimes|integer',
                'special_instructions' => 'nullable|string',
            ]);

            $cartItem = CartItem::where('admin_id', auth()->id())
                ->where('id', $cartItemId)->first();

            if (isset($validated['quantity'])) {
                if ($validated['quantity'] <= 0) {
                    $cartItem->delete();

                    return response()->json([
                        'success' => true,
                        'message' => 'Menuitem Deleted',
                    ]);
                }
                $validated['total_price'] = $cartItem->unit_price * $validated['quantity'];
            }

            $cartItem->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Menuitem updated successfully',
                'data' => $cartItem->load(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping'])
            ]);
        } catch (Exception $exception) {
            Log::error('show cart item fetching error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',

            ]);
        }
    }

    /**
     * Remove item from cart.
     */
    public function destroy(CartItem $cartItem)
    {
        $cartItem->delete();

        return response()->json(null, 204);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'order_type' => 'required|in:delivery,collection,dine_in,table,table_order',
            'payment_method' => 'required|in:stripe,cash,card,digital,apple_pay,google_pay',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'delivery_address' => 'nullable|string',
            'address_id' => 'nullable|exists:user_addresses,id',
            'table_id' => 'nullable|exists:restaurant_tables,id',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.menu_item_id' => 'required_with:items|exists:menu_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.size_id' => 'nullable|exists:item_sizes,id',
            'items.*.cooking_preference_id' => 'nullable|exists:cooking_preferences,id',
            'items.*.spice_level_id' => 'nullable|exists:spice_levels,id',
            'items.*.unit_price' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $user = $request->user() ?: (isset($validated['user_id']) ? User::find($validated['user_id']) : null);
            $cart = null;

            if (!empty($validated['cart_id'])) {
                $cart = Cart::with(['items.menuItem', 'items.size', 'items.cookingPreference', 'items.spiceLevel', 'items.toppings'])->find($validated['cart_id']);
            } elseif ($user) {
                $cart = Cart::with(['items.menuItem', 'items.size', 'items.cookingPreference', 'items.spiceLevel', 'items.toppings'])->where('user_id', $user->id)->latest()->first();
            }

            $orderNumber = 'ORD-' . strtoupper(Str::random(6));

            $branch = !empty($validated['branch_id'])
                ? Branch::find($validated['branch_id'])
                : null;

            $userAddress = null;
            if (!empty($validated['address_id'])) {
                $userAddress = UserAddress::find($validated['address_id']);
            } elseif ($user) {
                $userAddress = UserAddress::where('user_id', $user->id)
                    ->orderByDesc('is_default')
                    ->latest()
                    ->first();
            }

            $subtotal = 0;
            $orderItemsData = [];

            if ($cart && $cart->items->count() > 0) {
                foreach ($cart->items as $item) {
                    $itemSubtotal = $item->total_price;
                    $subtotal += $itemSubtotal;

                    $optionsSummary = [];
                    if ($item->size) $optionsSummary[] = $item->size->name;
                    if ($item->cookingPreference) $optionsSummary[] = $item->cookingPreference->name;
                    if ($item->spiceLevel) $optionsSummary[] = $item->spiceLevel->name;

                    $orderItemsData[] = [
                        'menu_item_id' => $item->menu_item_id,
                        'item_name' => $item->menuItem->name,
                        'size_id' => $item->size_id,
                        'size_name' => $item->size?->name,
                        'cooking_preference_id' => $item->cooking_preference_id,
                        'cooking_preference' => $item->cookingPreference?->name,
                        'spice_level_id' => $item->spice_level_id,
                        'spice_level' => $item->spiceLevel?->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $itemSubtotal,
                        'special_instructions' => $item->special_instructions,
                        'options_summary' => implode(', ', $optionsSummary),
                    ];
                }
            } elseif (!empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $menuItem = \App\Models\MenuItem::findOrFail($itemData['menu_item_id']);
                    $unitPrice = $itemData['unit_price'] ?? $menuItem->price;
                    $itemSubtotal = $unitPrice * $itemData['quantity'];
                    $subtotal += $itemSubtotal;

                    $orderItemsData[] = [
                        'menu_item_id' => $menuItem->id,
                        'item_name' => $menuItem->name,
                        'size_id' => $itemData['size_id'] ?? null,
                        'size_name' => isset($itemData['size_id']) ? \App\Models\ItemSize::find($itemData['size_id'])?->name : null,
                        'cooking_preference_id' => $itemData['cooking_preference_id'] ?? null,
                        'cooking_preference' => isset($itemData['cooking_preference_id']) ? \App\Models\CookingPreference::find($itemData['cooking_preference_id'])?->name : null,
                        'spice_level_id' => $itemData['spice_level_id'] ?? null,
                        'spice_level' => isset($itemData['spice_level_id']) ? \App\Models\SpiceLevel::find($itemData['spice_level_id'])?->name : null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $unitPrice,
                        'subtotal' => $itemSubtotal,
                        'special_instructions' => $itemData['special_instructions'] ?? null,
                        'options_summary' => null,
                    ];
                }
            }

            $vat = $subtotal > 0 ? 2.00 : 0.00;
            $deliveryFee = ($validated['order_type'] === 'delivery' && $subtotal > 0) ? 0.00 : 0.00;
            $tip = $validated['tip'] ?? 0;
            $riderTip = $validated['rider_tip'] ?? 0;

            // Handle Loyalty Points Discount
            $discount = 0;
            $loyaltyUsed = 0;
            if (!empty($validated['use_loyalty_points']) && $user && $user->loyalty_points_balance >= 100) {
                $loyaltyUsed = floor($user->loyalty_points_balance / 100) * 100;
                $discount = $loyaltyUsed / 100; // 100 points = £1 discount
                $user->decrement('loyalty_points_balance', $loyaltyUsed);

                LoyaltyPoint::create([
                    'user_id' => $user->id,
                    'points' => -$loyaltyUsed,
                    'type' => 'redeem',
                    'remarks' => 'Redeemed on Order #' . $orderNumber,
                ]);
            }

            $total = max(0, $subtotal + $vat + $deliveryFee + $tip + $riderTip - $discount);
            $loyaltyEarned = (int)floor($subtotal / 10) * 5;

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user?->id,
                'restaurant_id' => $branch?->restaurant_id,
                'branch_id' => $validated['branch_id'] ?? null,
                'address_id' => $userAddress?->id,
                'table_id' => $validated['table_id'] ?? null,
                'order_type' => $validated['order_type'],
                'order_status' => 'pending',
                'payment_status' => 'pending', // Auto-set paid for checkout simulation
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'vat' => $vat,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
                'tip' => $tip,
                'rider_tip' => $riderTip,
                'total' => $total,
                'loyalty_points_earned' => $loyaltyEarned,
                'loyalty_points_used' => $loyaltyUsed,
                'estimated_delivery_time' => now()->addMinutes(30),
                'customer_name' => $validated['customer_name'] ?? 'POS user',
                'customer_phone' => $validated['customer_phone'] ?? '8801XXXXXXX',
                'delivery_address' => $validated['delivery_address'] ?? $userAddress?->address ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Save order items
            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            // Award earned loyalty points to user
            if ($user && $loyaltyEarned > 0) {
                $user->increment('loyalty_points_balance', $loyaltyEarned);
                LoyaltyPoint::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'points' => $loyaltyEarned,
                    'type' => 'earn',
                    'remarks' => 'Earned from Order #' . $orderNumber,
                ]);
            }

            // Create Kitchen Order for KDS
            $station = KitchenStation::where('branch_id', $order->branch_id)->first();
            KitchenOrder::create([
                'order_id' => $order->id,
                'kitchen_station_id' => $station?->id,
                'status' => 'pending',
            ]);

            // Clear Cart if order was created from cart
            if ($cart) {
                $cart->items()->delete();
                $cart->update(['subtotal' => 0, 'vat' => 0, 'total' => 0]);
            }
                $payment = $order->payment()->create([
                    'method' =>  $request->payment_method ?? null,
                    'payment_method' => $request->payment_method ?? null,
                    'stripe_payment_intent' => null,
                    'transaction_id' => uniqid(),
                    'amount' => $order->total,
                    'currency' => 'usd',
                    'status' => 'successful',
                ]);


            // 3. Broadcast real-time order creation to Branch Admin / Next.js Kanban Board (all order types)
            if ($order->branch_id) {
                try {
                    broadcast(new OrderStatusUpdatedBroadcastEvent($order, 'created'));
                } catch (\Exception $e) {
                    Log::error('Order Created Broadcast Error: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Order placed Successfully',
                'data' => $order->load([
                    'items.menuItem',
                    'items.size',
                    'items.cookingPreference',
                    'items.spiceLevel',
                    'items.toppings',
                ]),
                'stripe' => $session ?? null
            ], 201);


        });
    }
}

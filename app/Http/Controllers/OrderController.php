<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\KitchenOrder;
use App\Models\KitchenStation;
use App\Models\LoyaltyPoint;
use App\Models\User;
use App\Models\Branch;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class OrderController extends Controller
{
    /**
     * Display a listing of orders with filters & pagination.
     */
    public function index(Request $request)
    {
        $query = Order::with([
            'items.menuItem',
            'items.size',
            'items.cookingPreference',
            'items.spiceLevel',
            'items.toppings.topping',
            'user',
            'branch',
            'assignedStaff',
            'assignedDriver',
            'delivery',
            'payment',
        ]);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('order_type')) {
            $query->where('order_type', $request->order_type);
        }

        if ($request->user() && $request->user()->isCustomer()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $query->latest();

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store/Checkout a newly created order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'cart_id' => 'nullable|exists:carts,id',
            'order_type' => 'required|in:delivery,collection,dine_in,table,table_order',
            'payment_method' => 'required|in:stripe,cash,card,digital,apple_pay,google_pay',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'delivery_address' => 'nullable|string',
            'address_id' => 'nullable|exists:user_addresses,id',
            'table_id' => 'nullable|exists:restaurant_tables,id',
            'notes' => 'nullable|string',
            'tip' => 'nullable|numeric|min:0',
            'rider_tip' => 'nullable|numeric|min:0',
            'use_loyalty_points' => 'nullable|boolean',
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
                'customer_name' => $validated['customer_name'] ?? $user?->name,
                'customer_phone' => $validated['customer_phone'] ?? $user?->phone,
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


            if ($order->payment_method === 'stripe') {

                $payment = $order->payment()->create([
                    'method' => 'stripe',
                    'payment_method' => 'stripe',
                    'stripe_payment_intent' => null,
                    'transaction_id' => uniqid(),
                    'amount' => $order->total,
                    'currency' => 'usd',
                    'status' => 'pending',
                ]);

                //Payment Gateway Starts
                $stripe = new StripeClient(config('services.stripe.secret'));

                $session = $stripe->checkout->sessions->create([
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Restaurant Menuitem Order',
                            ],
                            'unit_amount' => (int)($order->total * 100),
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',

                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],



                    // ✅ IMPORTANT: api + v1 prefix
                    'success_url' => url('/api/v1/order/success') . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => url('/api/v1/order/cancel'),
                ]);


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

    /**
     * Display the specified order.
     */
    public function show(Order $order)
    {
        return response()->json($order->load([
            'items.menuItem',
            'items.size',
            'items.cookingPreference',
            'items.spiceLevel',
            'items.toppings',
            'user',
            'branch',
            'assignedStaff',
            'assignedDriver',
            'delivery',
            'payment',
            'kitchenOrders',
        ]));
    }

    /**
     * Update the specified order in storage (status, driver assignment, etc).
     */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'order_status' => 'sometimes|in:pending,accepted,preparing,ready,out_for_delivery,completed,cancelled,refunded',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
            'assigned_staff_id' => 'nullable|exists:staff,id',
            'assigned_driver_id' => 'nullable|exists:drivers,id',
            'estimated_delivery_time' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $order->update($validated);

        // Update linked KitchenOrder status if order status changed
        if (isset($validated['order_status'])) {
            if ($validated['order_status'] === 'preparing') {
                $order->kitchenOrders()->update(['status' => 'preparing', 'started_at' => now()]);
            } elseif ($validated['order_status'] === 'ready') {
                $order->kitchenOrders()->update(['status' => 'ready', 'completed_at' => now()]);
            } elseif (in_array($validated['order_status'], ['completed', 'cancelled'])) {
                $order->kitchenOrders()->update(['status' => 'served']);
            }
        }

        return response()->json($order->load([
            'items.menuItem',
            'items.size',
            'items.cookingPreference',
            'items.spiceLevel',
            'assignedStaff',
            'assignedDriver',
        ]));
    }

    /**
     * Cancel/delete order.
     */
    public function destroy(Order $order)
    {
        $order->update(['order_status' => 'cancelled']);

        return response()->json(['message' => 'Order cancelled successfully']);
    }


}

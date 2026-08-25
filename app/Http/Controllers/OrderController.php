<?php

namespace App\Http\Controllers;

use App\Events\NewDeliveryBroadcastEvent;
use App\Events\OrderAcceptedBroadcastEvent;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\KitchenOrder;
use App\Models\KitchenStation;
use App\Models\LoyaltyPoint;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        if ($request->boolean('unassigned') || $request->boolean('unassigned_only')) {
            $query->whereNull('assigned_driver_id');
        } elseif ($request->filled('assigned_driver_id')) {
            $query->where('assigned_driver_id', $request->assigned_driver_id);
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

            // Broadcast to Reverb WebSocket & Send FCM Push for Delivery orders
            if ($order->order_type === 'delivery' && $order->branch_id) {
                try {
                    // 1. Reverb WebSocket Broadcast to driver channel
                    broadcast(new NewDeliveryBroadcastEvent($order));

                    // 2. FCM Push Notification to Online Drivers of this Branch (from users.fcm_token)
                    $onlineDriverTokens = User::whereHas('driver', function ($q) use ($order) {
                        $q->where('branch_id', $order->branch_id)
                          ->where('kyc_status', 'approved')
                          ->where('is_online', true)
                          ->where('status', 'available');
                    })
                    ->whereNotNull('fcm_token')
                    ->pluck('fcm_token')
                    ->toArray();

                    if (!empty($onlineDriverTokens)) {
                        FirebaseNotificationService::sendPushNotification(
                            $onlineDriverTokens,
                            "New Delivery Task Available!",
                            "Order #{$order->order_number} is available for delivery (£" . number_format((float)$order->delivery_fee, 2) . "). Tap to accept!",
                            [
                                'type' => 'new_delivery',
                                'order_id' => (string) $order->id,
                                'order_number' => $order->order_number,
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Delivery Dispatch Broadcast/FCM Error: ' . $e->getMessage());
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

        // Handle Manual Driver Assignment by Admin
        if (!empty($validated['assigned_driver_id'])) {
            $driverId = $validated['assigned_driver_id'];
            $driver = Driver::with('user')->find($driverId);

            if ($driver) {
                // Update driver status
                $driver->update(['status' => 'on_delivery']);

                // Create or update delivery record
                Delivery::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'driver_id' => $driver->id,
                        'delivery_status' => 'assigned',
                        'estimated_time' => $validated['estimated_delivery_time'] ?? now()->addMinutes(30),
                    ]
                );

                // Notify Assigned Driver
                try {
                    $driverUserId = $driver->user_id;
                    if ($driverUserId) {
                        Notification::create([
                            'user_id' => $driverUserId,
                            'branch_id' => $order->branch_id,
                            'title' => 'New Delivery Task Assigned',
                            'message' => "You have been assigned to deliver order #{$order->order_number}.",
                            'type' => 'delivery',
                            'is_read' => false,
                        ]);
                    }

                    $driverFcmToken = $driver->user?->fcm_token;
                    if ($driverFcmToken) {
                        FirebaseNotificationService::sendPushNotification(
                            $driverFcmToken,
                            "New Delivery Task Assigned!",
                            "You have been assigned to deliver order #{$order->order_number} (£" . number_format((float)$order->delivery_fee, 2) . ").",
                            ['type' => 'delivery_assigned', 'order_id' => (string) $order->id]
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Driver Assignment Notification Error: ' . $e->getMessage());
                }

                // Notify Customer
                if ($order->user_id) {
                    try {
                        Notification::create([
                            'user_id' => $order->user_id,
                            'branch_id' => $order->branch_id,
                            'title' => 'Driver Assigned to Your Order',
                            'message' => "Driver '{$driver->name}' has been assigned to deliver your order #{$order->order_number}.",
                            'type' => 'order',
                            'is_read' => false,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Customer Driver Assignment Notification Error: ' . $e->getMessage());
                    }
                }

                // Broadcast Reverb WebSocket event to dismiss card on other drivers' screens
                try {
                    broadcast(new OrderAcceptedBroadcastEvent($order, $driver));
                } catch (\Exception $e) {
                    Log::error('Admin Order Assigned Broadcast Error: ' . $e->getMessage());
                }
            }
        }

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
    /**
     * Dedicated Action: Manually assign driver to order (Super Admin / Branch Admin).
     */
    public function assignDriver(Request $request, Order $order)
    {
        $authUser = $request->user();
        if (!$authUser || (!$authUser->isSuperAdmin() && !$authUser->isBranchAdmin())) {
            return response()->json(['message' => 'Unauthorized: Only admins can assign drivers.'], 403);
        }

        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'order_status' => 'nullable|in:pending,accepted,preparing,ready,out_for_delivery,delivered,completed',
            'delivery_status' => 'nullable|in:assigned,picked_up,on_the_way,delivered,failed',
            'estimated_delivery_time' => 'nullable|date',
        ]);

        $driver = Driver::with('user')->find($validated['driver_id']);

        if (!$driver) {
            return response()->json(['message' => 'Driver not found.'], 404);
        }

        if ($driver->kyc_status !== 'approved') {
            return response()->json(['message' => 'Cannot assign: Driver KYC is not approved yet.'], 422);
        }

        return DB::transaction(function () use ($order, $driver, $validated) {
            // 1. Assign driver & update order status
            $nextStatus = $validated['order_status'] ?? (in_array($order->order_status, ['pending', 'accepted']) ? 'accepted' : $order->order_status);
            $order->update([
                'assigned_driver_id' => $driver->id,
                'order_status' => $nextStatus,
                'estimated_delivery_time' => $validated['estimated_delivery_time'] ?? $order->estimated_delivery_time,
            ]);

            // 2. Update driver status
            $driver->update(['status' => 'on_delivery']);

            // 3. Create or update delivery record
            $delivery = Delivery::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'driver_id' => $driver->id,
                    'delivery_status' => $validated['delivery_status'] ?? 'assigned',
                    'estimated_time' => $validated['estimated_delivery_time'] ?? now()->addMinutes(30),
                ]
            );

            // 4. Notify Driver (In-App & FCM Push)
            try {
                if ($driver->user_id) {
                    Notification::create([
                        'user_id' => $driver->user_id,
                        'branch_id' => $order->branch_id,
                        'title' => 'New Delivery Task Assigned',
                        'message' => "You have been assigned to deliver order #{$order->order_number}.",
                        'type' => 'delivery',
                        'is_read' => false,
                    ]);
                }

                $driverToken = $driver->user?->fcm_token;
                if ($driverToken) {
                    FirebaseNotificationService::sendPushNotification(
                        $driverToken,
                        "New Delivery Task Assigned!",
                        "You have been assigned to deliver order #{$order->order_number} (£" . number_format((float)$order->delivery_fee, 2) . ").",
                        ['type' => 'delivery_assigned', 'order_id' => (string) $order->id]
                    );
                }
            } catch (\Exception $e) {
                Log::error('Driver Assignment Notification Error: ' . $e->getMessage());
            }

            // 5. Notify Customer (In-App)
            if ($order->user_id) {
                try {
                    Notification::create([
                        'user_id' => $order->user_id,
                        'branch_id' => $order->branch_id,
                        'title' => 'Driver Assigned to Your Order',
                        'message' => "Driver '{$driver->name}' has been assigned to deliver your order #{$order->order_number}.",
                        'type' => 'order',
                        'is_read' => false,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Customer Driver Assignment Notification Error: ' . $e->getMessage());
                }
            }

            // 6. Broadcast to dismiss from other drivers' screens
            try {
                broadcast(new OrderAcceptedBroadcastEvent($order, $driver));
            } catch (\Exception $e) {
                Log::error('Admin Order Assigned Broadcast Error: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => "Driver '{$driver->name}' has been assigned to order #{$order->order_number} successfully.",
                'order' => $order->fresh(['assignedDriver', 'delivery']),
                'delivery' => $delivery->fresh(['driver']),
            ]);
        });
    }
}

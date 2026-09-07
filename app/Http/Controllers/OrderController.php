<?php

namespace App\Http\Controllers;

use App\Events\NewDeliveryBroadcastEvent;
use App\Events\OrderAcceptedBroadcastEvent;
use App\Events\OrderStatusUpdatedBroadcastEvent;
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

        $authUser = $request->user() ?? auth('sanctum')->user();
        $isSuperAdmin = $authUser && ($authUser->isSuperAdmin() || $authUser->user_type === 'super_admin' || $authUser->user_type === 'admin' || $authUser->hasRole(['super_admin', 'Super Admin', 'admin']));

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        } elseif (!$isSuperAdmin && $authUser) {
            if ($authUser->isCustomer() || $authUser->user_type === 'customer' || $authUser->hasRole('Customer')) {
                $query->where('user_id', $authUser->id);
            } elseif ($authUser->isBranchAdmin() || in_array($authUser->user_type, ['branch_admin', 'staff', 'driver']) || (method_exists($authUser, 'hasRole') && $authUser->hasRole(['Branch Manager', 'branch_admin', 'Cashier', 'cashier', 'Chef', 'chef', 'Waiter', 'waiter', 'Delivery Driver', 'driver']))) {
                // Automatically detect branch for branch-scoped staff and managers
                $userBranchId = $authUser->branch_id
                    ?? \App\Models\BranchAdmin::where('email', $authUser->email)->value('branch_id')
                    ?? \App\Models\Staff::where('email', $authUser->email)->value('branch_id')
                    ?? $authUser->driver?->branch_id;

                if ($userBranchId) {
                    $query->where('branch_id', $userBranchId);
                }
            }
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
        } elseif ($request->boolean('assigned') || $request->boolean('assigned_only')) {
            $query->whereNotNull('assigned_driver_id');
        } elseif ($request->filled('assigned_driver_id')) {
            $query->where('assigned_driver_id', $request->assigned_driver_id);
        }

        // Date & Period Filter (Today, Yesterday, Weekly, WTD, Monthly, MTD, Yearly, YTD, History, Custom)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->filled('period')) {
            $period = strtolower($request->period);
            $now = \Carbon\Carbon::now();
            if ($period === 'today') {
                $query->whereDate('created_at', \Carbon\Carbon::today());
            } elseif ($period === 'yesterday') {
                $query->whereDate('created_at', \Carbon\Carbon::yesterday());
            } elseif ($period === 'wtd' || $period === 'week_to_date') {
                $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now]);
            } elseif ($period === 'weekly') {
                $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
            } elseif ($period === 'mtd' || $period === 'month_to_date') {
                $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now]);
            } elseif ($period === 'monthly') {
                $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
            } elseif ($period === 'ytd' || $period === 'year_to_date') {
                $query->whereBetween('created_at', [$now->copy()->startOfYear(), $now]);
            } elseif ($period === 'yearly') {
                $query->whereBetween('created_at', [$now->copy()->startOfYear(), $now->copy()->endOfYear()]);
            } elseif (in_array($period, ['history', 'all', 'all_history'])) {
                // Return all orders history without date restriction
            }
        }

        // Support filtering specifically for completed/past order history
        if ($request->boolean('history_only') || $request->input('type') === 'history' || $request->input('view') === 'history') {
            $query->whereIn('order_status', ['completed', 'delivered', 'cancelled', 'rejected']);
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

//                $session = $stripe->checkout->sessions->create([
//                    'line_items' => [[
//                        'price_data' => [
//                            'currency' => 'usd',
//                            'product_data' => [
//                                'name' => 'Restaurant Menuitem Order',
//                            ],
//                            'unit_amount' => (int)($order->total * 100),
//                        ],
//                        'quantity' => 1,
//                    ]],
//                    'mode' => 'payment',
//
//                    'metadata' => [
//                        'payment_id' => $payment->id,
//                    ],
//
//
//
//                    // ✅ IMPORTANT: api + v1 prefix
//                    'success_url' => url('/api/v1/order/success') . '?session_id={CHECKOUT_SESSION_ID}',
//                    'cancel_url' => url('/api/v1/order/cancel'),
//                ]);

                $session = $stripe->checkout->sessions->create([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Restaurant Menuitem Order',
                            ],
                            // Ensure integer cents casting safely
                            'unit_amount' => (int) round($order->total * 100),
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'metadata' => [
                        'payment_id' => (string) $payment->id,
                        'order_id'   => (string) $order->id,
                    ],
                    // Frontend redirect routes (Customer browser flow)
                    'success_url' => url('/api/v1/order/success') .'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => url('/api/v1/order/cancel'),
                ]);
            }

            // 1. Broadcast real-time order creation to Branch Admin & Kitchen (Kanban / POS)
            if ($order->branch_id) {
                try {
                    broadcast(new OrderStatusUpdatedBroadcastEvent($order, 'created'));
                } catch (\Exception $e) {
                    Log::error('Order Created Broadcast Error: ' . $e->getMessage());
                }

                // 2. In-app Notification to Branch Admin about pending order requiring approval
                try {
                    $branchAdminUsers = User::where('branch_id', $order->branch_id)
                        ->whereIn('user_type', ['branch_admin', 'staff'])
                        ->pluck('id');

                    foreach ($branchAdminUsers as $adminUserId) {
                        Notification::create([
                            'user_id' => $adminUserId,
                            'branch_id' => $order->branch_id,
                            'title' => 'New Pending Order #' . $order->order_number,
                            'message' => "A new {$order->order_type} order #{$order->order_number} has been placed. Please review and approve/prepare.",
                            'type' => 'order',
                            'is_read' => false,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Branch Admin Order Notification Error: ' . $e->getMessage());
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
    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        // Customer can only view their own order
        if ($user && $user->isCustomer() && $order->user_id && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to this order.'], 403);
        }

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
        // Disallow updating or reactivating an already cancelled/rejected order
        if ($order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->order_number} has already been cancelled/rejected and cannot be updated or reactivated.",
            ], 422);
        }

        $validated = $request->validate([
            'order_status' => 'sometimes|in:pending,accepted,preparing,ready,out_for_delivery,completed,cancelled,refunded',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
            'assigned_staff_id' => 'nullable|exists:staff,id',
            'assigned_driver_id' => 'nullable|exists:drivers,id',
            'estimated_delivery_time' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Handle Manual Driver Assignment by Admin
        if (!empty($validated['assigned_driver_id'])) {
            $driverId = $validated['assigned_driver_id'];
            $driver = Driver::with('user')->find($driverId);

            if ($driver) {
                $hasActiveDelivery = Delivery::where('driver_id', $driver->id)
                    ->whereIn('delivery_status', ['assigned', 'picked_up', 'on_the_way'])
                    ->where('order_id', '!=', $order->id)
                    ->exists();

                if ($hasActiveDelivery) {
                    return response()->json([
                        'success' => false,
                        'message' => "Driver '{$driver->name}' is currently on an active delivery task and cannot be assigned.",
                    ], 422);
                }
            }
        }

        $order->update($validated);

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

        // Update linked KitchenOrder status and dispatch notifications on status change
        if (isset($validated['order_status'])) {
            $newStatus = $validated['order_status'];

            if ($newStatus === 'preparing') {
                $order->kitchenOrders()->update(['status' => 'preparing', 'started_at' => now()]);
            } elseif ($newStatus === 'ready') {
                $order->kitchenOrders()->update(['status' => 'ready', 'completed_at' => now()]);
            } elseif (in_array($newStatus, ['completed', 'cancelled'])) {
                $order->kitchenOrders()->update(['status' => 'served']);
            }

            // Dispatch to Drivers ONLY when Branch approves and status becomes 'preparing' or 'ready'
            if (in_array($newStatus, ['preparing', 'ready', 'accepted']) && $order->order_type === 'delivery' && empty($order->assigned_driver_id) && $order->branch_id) {
                try {
                    // 1. Reverb WebSocket Broadcast to Driver Channel (Upcoming Request popup)
                    broadcast(new NewDeliveryBroadcastEvent($order));

                    // 2. FCM Push Notification to Online & Available Drivers of this Branch
                    $onlineDrivers = User::whereHas('driver', function ($q) use ($order) {
                        $q->where('branch_id', $order->branch_id)
                          ->where('kyc_status', 'approved')
                          ->where('is_online', true)
                          ->where('status', 'available');
                    })->get();

                    $onlineDriverTokens = $onlineDrivers->whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

                    if (!empty($onlineDriverTokens)) {
                        FirebaseNotificationService::sendPushNotification(
                            $onlineDriverTokens,
                            "New Delivery Task Available!",
                            "Order #{$order->order_number} is {$newStatus} and available for delivery (£" . number_format((float)$order->delivery_fee, 2) . "). Tap to accept!",
                            [
                                'type' => 'new_delivery',
                                'order_id' => (string) $order->id,
                                'order_number' => $order->order_number,
                            ]
                        );
                    }

                    // 3. In-App Notification to Drivers
                    foreach ($onlineDrivers as $driverUser) {
                        Notification::create([
                            'user_id' => $driverUser->id,
                            'branch_id' => $order->branch_id,
                            'title' => 'New Delivery Request Available',
                            'message' => "Order #{$order->order_number} is {$newStatus} and available for delivery.",
                            'type' => 'delivery',
                            'is_read' => false,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Driver Delivery Dispatch on Preparing/Ready Error: ' . $e->getMessage());
                }
            }

            // Customer Notifications on Status Transitions
            if ($order->user_id) {
                try {
                    if (in_array($newStatus, ['accepted', 'preparing'])) {
                        Notification::create([
                            'user_id' => $order->user_id,
                            'branch_id' => $order->branch_id,
                            'title' => 'Order Approved & Preparing',
                            'message' => "Your order #{$order->order_number} has been approved and is now being prepared in the kitchen.",
                            'type' => 'order',
                            'is_read' => false,
                        ]);
                    } elseif ($newStatus === 'cancelled') {
                        Notification::create([
                            'user_id' => $order->user_id,
                            'branch_id' => $order->branch_id,
                            'title' => 'Order Cancelled',
                            'message' => "Your order #{$order->order_number} has been cancelled.",
                            'type' => 'order',
                            'is_read' => false,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Customer Status Transition Notification Error: ' . $e->getMessage());
                }
            }
        }

        // Broadcast real-time order update to Branch Admin / Next.js Kanban Board
        if ($order->branch_id) {
            try {
                broadcast(new OrderStatusUpdatedBroadcastEvent($order, 'updated'));
            } catch (\Exception $e) {
                Log::error('Order Update Broadcast Error: ' . $e->getMessage());
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

        if ($order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => "Cannot assign driver: Order #{$order->order_number} is already cancelled.",
            ], 422);
        }

        if (in_array($order->order_status, ['delivered', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot assign driver: Order #{$order->order_number} has already been completed/delivered.",
            ], 422);
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

        $hasActiveDelivery = Delivery::where('driver_id', $driver->id)
            ->whereIn('delivery_status', ['assigned', 'picked_up', 'on_the_way'])
            ->where('order_id', '!=', $order->id)
            ->exists();

        if ($hasActiveDelivery) {
            return response()->json([
                'success' => false,
                'message' => "Cannot assign: Driver '{$driver->name}' is currently on an active delivery task."
            ], 422);
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

            // 7. Broadcast real-time order update to Branch Admin / Next.js Kanban Board
            if ($order->branch_id) {
                try {
                    broadcast(new OrderStatusUpdatedBroadcastEvent($order, 'assigned'));
                } catch (\Exception $e) {
                    Log::error('Order Assigned Broadcast Error: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Driver '{$driver->name}' has been assigned to order #{$order->order_number} successfully.",
                'order' => $order->fresh(['assignedDriver', 'delivery']),
                'delivery' => $delivery->fresh(['driver']),
            ]);
        });
    }

    /**
     * Dedicated Action: Branch Admin Approves/Accepts a Pending Order.
     */
    public function approve(Request $request, Order $order)
    {
        $authUser = $request->user();
        $isAllowed = $authUser && (
            $authUser->isSuperAdmin() ||
            $authUser->user_type === 'super_admin' ||
            $authUser->user_type === 'admin' ||
            $authUser->isBranchAdmin() ||
            $authUser->user_type === 'branch_admin' ||
            $authUser->user_type === 'staff' ||
            (method_exists($authUser, 'hasRole') && $authUser->hasRole(['super_admin', 'Super Admin', 'admin', 'branch_admin', 'Branch Manager', 'branch_manager', 'Cashier', 'cashier', 'Chef', 'chef']))
        );

        if (!$isAllowed) {
            return response()->json(['message' => 'Unauthorized: Only Super Admins, Branch Admins, and authorized staff can approve orders.'], 403);
        }

        // Check if the order is already cancelled
        if ($order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->order_number} is cancelled and cannot be approved.",
            ], 422);
        }

        // Only pending orders can be approved
        if ($order->order_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->order_number} cannot be approved because its current status is '{$order->order_status}'. Only pending orders can be approved.",
            ], 422);
        }

        $validated = $request->validate([
            'preparation_time' => 'nullable|integer|min:1|max:180', // in minutes
            'notes' => 'nullable|string|max:500',
        ]);

        $prepTime = $validated['preparation_time'] ?? 25;
        $estimatedDeliveryTime = now()->addMinutes($prepTime + ($order->order_type === 'delivery' ? 20 : 0));

        DB::transaction(function () use ($order, $prepTime, $estimatedDeliveryTime, $validated) {
            // 1. Update Order Status to 'preparing'
            $order->update([
                'order_status' => 'preparing',
                'estimated_delivery_time' => $estimatedDeliveryTime,
                'notes' => !empty($validated['notes']) ? ($order->notes ? $order->notes . ' | ' . $validated['notes'] : $validated['notes']) : $order->notes,
            ]);

            // 2. Update Kitchen Station Orders to 'preparing'
            $order->kitchenOrders()->update([
                'status' => 'preparing',
                'started_at' => now(),
            ]);

            // 3. If Order Type is Delivery & unassigned, Dispatch to Branch Online Drivers
            if ($order->order_type === 'delivery' && empty($order->assigned_driver_id) && $order->branch_id) {
                try {
                    // Reverb WebSocket Broadcast
                    broadcast(new NewDeliveryBroadcastEvent($order));

                    // FCM Push Notification to Online & Approved Drivers
                    $onlineDrivers = User::whereHas('driver', function ($q) use ($order) {
                        $q->where('branch_id', $order->branch_id)
                          ->where('kyc_status', 'approved')
                          ->where('is_online', true)
                          ->where('status', 'available');
                    })->get();

                    $onlineDriverTokens = $onlineDrivers->whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

                    if (!empty($onlineDriverTokens)) {
                        FirebaseNotificationService::sendPushNotification(
                            $onlineDriverTokens,
                            "New Delivery Task Available!",
                            "Order #{$order->order_number} is approved & preparing (£" . number_format((float)$order->delivery_fee, 2) . "). Tap to accept!",
                            [
                                'type' => 'new_delivery',
                                'order_id' => (string) $order->id,
                                'order_number' => $order->order_number,
                            ]
                        );
                    }

                    // In-app notifications to drivers
                    foreach ($onlineDrivers as $driverUser) {
                        Notification::create([
                            'user_id' => $driverUser->id,
                            'branch_id' => $order->branch_id,
                            'title' => 'New Delivery Request Available',
                            'message' => "Order #{$order->order_number} has been approved and is available for delivery.",
                            'type' => 'delivery',
                            'is_read' => false,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Driver Delivery Dispatch on Order Approve Error: ' . $e->getMessage());
                }
            }

            // 4. Notify Customer that Order has been Approved and is Preparing (In-App & FCM Push)
            if ($order->user_id) {
                try {
                    Notification::create([
                        'user_id' => $order->user_id,
                        'branch_id' => $order->branch_id,
                        'title' => 'Order Approved & Preparing',
                        'message' => "Great news! Your order #{$order->order_number} has been accepted by the restaurant and is now being prepared.",
                        'type' => 'order',
                        'is_read' => false,
                    ]);

                    $custToken = $order->user?->fcm_token;
                    if ($custToken) {
                        FirebaseNotificationService::sendPushNotification(
                            $custToken,
                            "Order Approved & Preparing! 🍳",
                            "Your order #{$order->order_number} has been accepted and is now being prepared.",
                            ['type' => 'order_status', 'order_id' => (string) $order->id, 'order_status' => 'preparing']
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Customer Notification Error on Order Approve: ' . $e->getMessage());
                }
            }

            // 5. Broadcast status update to Kanban Board / Web Dashboard
            if ($order->branch_id) {
                try {
                    broadcast(new OrderStatusUpdatedBroadcastEvent($order, 'preparing'));
                } catch (\Exception $e) {
                    Log::error('Order Approved Broadcast Error: ' . $e->getMessage());
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Order #{$order->order_number} approved successfully and sent for preparation.",
            'data' => $order->fresh(['items.menuItem', 'branch', 'user', 'kitchenOrders']),
        ]);
    }

    /**
     * Dedicated Action: Branch Admin Rejects/Cancels a Pending Order.
     */
    public function reject(Request $request, Order $order)
    {
        $authUser = $request->user();
        $isAllowed = $authUser && (
            $authUser->isSuperAdmin() ||
            $authUser->user_type === 'super_admin' ||
            $authUser->user_type === 'admin' ||
            $authUser->isBranchAdmin() ||
            $authUser->user_type === 'branch_admin' ||
            $authUser->user_type === 'staff' ||
            (method_exists($authUser, 'hasRole') && $authUser->hasRole(['super_admin', 'Super Admin', 'admin', 'branch_admin', 'Branch Manager', 'branch_manager', 'Cashier', 'cashier', 'Chef', 'chef']))
        );

        if (!$isAllowed) {
            return response()->json(['message' => 'Unauthorized: Only Super Admins, Branch Admins, and authorized staff can reject orders.'], 403);
        }

        // Check if the order is already cancelled / rejected
        if ($order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->order_number} is already cancelled and cannot be rejected again.",
            ], 422);
        }

        // Only pending orders can be rejected
        if ($order->order_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->order_number} cannot be rejected because its current status is '{$order->order_status}'. Only pending orders can be rejected.",
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reason = $validated['reason'] ?? 'Restaurant is unable to fulfill this order at the moment.';

        DB::transaction(function () use ($order, $reason) {
            // 1. Update Order Status to 'cancelled' and set rejection_reason directly on orders table
            $order->update([
                'order_status' => 'cancelled',
                'rejection_reason' => $reason,
                'notes' => $order->notes ? $order->notes . " | Rejection Reason: {$reason}" : "Rejection Reason: {$reason}",
            ]);

            // 2. Update Kitchen Station Orders to 'served' / cancelled
            $order->kitchenOrders()->update([
                'status' => 'served',
            ]);

            // 3. Notify Customer with Rejection Reason (In-App & FCM Push)
            if ($order->user_id) {
                try {
                    Notification::create([
                        'user_id' => $order->user_id,
                        'branch_id' => $order->branch_id,
                        'title' => 'Order Cancelled',
                        'message' => "We are sorry, your order #{$order->order_number} could not be accepted. Reason: {$reason}",
                        'type' => 'order',
                        'is_read' => false,
                    ]);

                    $custToken = $order->user?->fcm_token;
                    if ($custToken) {
                        FirebaseNotificationService::sendPushNotification(
                            $custToken,
                            "Order Cancelled",
                            "Your order #{$order->order_number} could not be accepted. Reason: {$reason}",
                            ['type' => 'order_status', 'order_id' => (string) $order->id, 'order_status' => 'cancelled']
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Customer Notification Error on Order Reject: ' . $e->getMessage());
                }
            }

            // 4. Broadcast status update to Kanban Board / Web Dashboard
            if ($order->branch_id) {
                try {
                    broadcast(new OrderStatusUpdatedBroadcastEvent($order, 'cancelled'));
                } catch (\Exception $e) {
                    Log::error('Order Rejected Broadcast Error: ' . $e->getMessage());
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Order #{$order->order_number} has been rejected.",
            'data' => $order->fresh(['items.menuItem', 'branch', 'user']),
        ]);
    }
}

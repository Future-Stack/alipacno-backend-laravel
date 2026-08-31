<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdatedBroadcastEvent;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Order;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    /**
     * Display a listing of deliveries.
     */
    public function index(Request $request)
    {
        $query = Delivery::with([
            'order.items.menuItem',
            'order.branch',
            'order.user.defaultAddress',
            'order.user.address',
            'order.address',
            'driver.user',
            'user.defaultAddress',
            'user.address',
        ]);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('order', function ($oq) use ($search) {
                    $oq->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                })->orWhereHas('driver', function ($dq) use ($search) {
                    $dq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($duq) use ($search) {
                            $duq->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created delivery assignment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'delivery_status' => 'nullable|in:assigned,picked_up,on_the_way,delivered,failed',
            'estimated_time' => 'nullable|date',
        ]);

        $order = Order::find($validated['order_id']);
        if ($order && in_array($order->order_status, ['delivered', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->order_number} has already been delivered/completed.",
            ], 422);
        }

        if ($order && $order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->order_number} is cancelled. Cannot create a delivery.",
            ], 422);
        }

        $delivery = Delivery::create($validated);

        if (!empty($validated['driver_id'])) {
            Driver::where('id', $validated['driver_id'])->update(['status' => 'on_delivery']);
            Order::where('id', $validated['order_id'])->update([
                'assigned_driver_id' => $validated['driver_id'],
                'order_status' => 'out_for_delivery'
            ]);
        }

        return response()->json($delivery->load([
            'order.items.menuItem',
            'order.branch',
            'order.user.defaultAddress',
            'order.user.address',
            'order.address',
            'driver.user',
            'user.defaultAddress',
            'user.address',
        ]), 201);
    }

    /**
     * Display the specified delivery details.
     */
    public function show(Delivery $delivery)
    {
        return response()->json($delivery->load([
            'order.items.menuItem',
            'order.branch',
            'order.user.defaultAddress',
            'order.user.address',
            'order.address',
            'driver.user',
            'user.defaultAddress',
            'user.address',
        ]));
    }

    /**
     * Update the specified delivery status.
     */
    public function update(Request $request, Delivery $delivery)
    {
        // 1. Check if delivery is already delivered
        if ($delivery->delivery_status === 'delivered') {
            $orderNumber = $delivery->order?->order_number ?? $delivery->order_id;
            return response()->json([
                'success' => false,
                'message' => "This delivery for Order #{$orderNumber} has already been delivered and cannot be updated again.",
            ], 422);
        }

        // 2. Check if the associated order is already completed or cancelled
        if ($delivery->order && in_array($delivery->order->order_status, ['delivered', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => "Order #{$delivery->order->order_number} has already been completed/delivered. Delivery status cannot be changed.",
            ], 422);
        }

        if ($delivery->order && $delivery->order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => "Order #{$delivery->order->order_number} is cancelled. Delivery status cannot be changed.",
            ], 422);
        }

        $validated = $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
            'delivery_status' => 'required|in:assigned,picked_up,on_the_way,delivered,failed',
            'estimated_time' => 'nullable|date',
        ]);

        $status = $validated['delivery_status'];
        $order = $delivery->order;
        $driverId = $validated['driver_id'] ?? $delivery->driver_id;
        $driver = $driverId ? Driver::find($driverId) : $delivery->driver;
        $driverName = $driver?->name ?? 'Driver';

        if ($status === 'assigned') {
            if ($driverId) {
                Driver::where('id', $driverId)->update(['status' => 'on_delivery']);
                $delivery->order()?->update([
                    'assigned_driver_id' => $driverId,
                    'order_status' => 'accepted',
                ]);
            }
        } elseif ($status === 'picked_up' || $status === 'on_the_way') {
            if (!$delivery->pickup_time) {
                $validated['pickup_time'] = now();
            }
            if ($driverId) {
                Driver::where('id', $driverId)->update(['status' => 'on_delivery']);
            }
            $delivery->order()?->update([
                'assigned_driver_id' => $driverId,
                'order_status' => 'out_for_delivery',
            ]);

            // Notify Customer (In-App & FCM Push)
            if ($order && $order->user_id) {
                try {
                    $notifTitle = "Order Out for Delivery 🛵";
                    $notifMessage = "Your order #{$order->order_number} has been picked up by {$driverName} and is on the way!";

                    Notification::create([
                        'user_id' => $order->user_id,
                        'branch_id' => $order->branch_id,
                        'title' => $notifTitle,
                        'message' => $notifMessage,
                        'type' => 'order',
                        'is_read' => false,
                    ]);

                    $customerToken = $order->user?->fcm_token;
                    if ($customerToken) {
                        FirebaseNotificationService::sendPushNotification(
                            $customerToken,
                            $notifTitle,
                            $notifMessage,
                            ['type' => 'order_status', 'order_id' => (string) $order->id, 'status' => 'out_for_delivery']
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Customer Pickup Notification Error: ' . $e->getMessage());
                }
            }
        } elseif ($status === 'delivered') {
            $validated['delivered_time'] = now();
            $delivery->order()?->update(['order_status' => 'completed', 'payment_status' => 'paid']);
            if ($driverId) {
                Driver::where('id', $driverId)->update(['status' => 'available']);
            }

            // Notify Customer (In-App & FCM Push)
            if ($order && $order->user_id) {
                try {
                    $notifTitle = "Order Delivered 🎉";
                    $notifMessage = "Your order #{$order->order_number} has been delivered. Enjoy your meal!";

                    Notification::create([
                        'user_id' => $order->user_id,
                        'branch_id' => $order->branch_id,
                        'title' => $notifTitle,
                        'message' => $notifMessage,
                        'type' => 'order',
                        'is_read' => false,
                    ]);

                    $customerToken = $order->user?->fcm_token;
                    if ($customerToken) {
                        FirebaseNotificationService::sendPushNotification(
                            $customerToken,
                            $notifTitle,
                            $notifMessage,
                            ['type' => 'order_status', 'order_id' => (string) $order->id, 'status' => 'delivered']
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Customer Delivered Notification Error: ' . $e->getMessage());
                }
            }
        } elseif ($status === 'failed') {
            $delivery->order()?->update(['order_status' => 'cancelled']);
            if ($driverId) {
                Driver::where('id', $driverId)->update(['status' => 'available']);
            }
        }

        if (isset($validated['driver_id']) && $validated['driver_id'] !== $delivery->driver_id) {
            // Free previous driver if changed
            if ($delivery->driver_id && $delivery->driver_id !== $validated['driver_id']) {
                Driver::where('id', $delivery->driver_id)->update(['status' => 'available']);
            }
            // Mark new driver as on delivery
            if (!empty($validated['driver_id'])) {
                Driver::where('id', $validated['driver_id'])->update(['status' => 'on_delivery']);
                $delivery->order()?->update(['assigned_driver_id' => $validated['driver_id']]);
            }
        }

        $delivery->update($validated);

        // Broadcast real-time order update to Branch Kanban Board
        if ($delivery->order && $delivery->order->branch_id) {
            try {
                broadcast(new OrderStatusUpdatedBroadcastEvent($delivery->order->fresh(), 'delivery_status_updated'));
            } catch (\Exception $e) {
                Log::error('Delivery Order Status Broadcast Error: ' . $e->getMessage());
            }
        }

        return response()->json($delivery->load([
            'order.items.menuItem',
            'order.branch',
            'order.user.defaultAddress',
            'order.user.address',
            'order.address',
            'driver.user',
            'user.defaultAddress',
            'user.address',
        ]));
    }

    /**
     * Remove the specified delivery.
     */
    public function destroy(Delivery $delivery)
    {
        if ($delivery->driver_id) {
            Driver::where('id', $delivery->driver_id)->update(['status' => 'available']);
        }

        $delivery->delete();

        return response()->json(null, 204);
    }
}
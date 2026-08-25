<?php

namespace App\Http\Controllers;

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
        $query = Delivery::with(['order.items.menuItem', 'order.branch', 'driver']);

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

        $delivery = Delivery::create($validated);

        if (!empty($validated['driver_id'])) {
            Driver::where('id', $validated['driver_id'])->update(['status' => 'on_delivery']);
            Order::where('id', $validated['order_id'])->update([
                'assigned_driver_id' => $validated['driver_id'],
                'order_status' => 'out_for_delivery'
            ]);
        }

        return response()->json($delivery->load(['order', 'driver']), 201);
    }

    /**
     * Display the specified delivery details.
     */
    public function show(Delivery $delivery)
    {
        return response()->json($delivery->load(['order.items.menuItem', 'order.branch', 'driver']));
    }

    /**
     * Update the specified delivery status.
     */
    public function update(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
            'delivery_status' => 'required|in:assigned,picked_up,on_the_way,delivered,failed',
            'estimated_time' => 'nullable|date',
        ]);

        $status = $validated['delivery_status'];
        $order = $delivery->order;
        $driver = $delivery->driver;
        $driverName = $driver?->name ?? 'Driver';

        if (($status === 'picked_up' || $status === 'on_the_way') && !$delivery->pickup_time) {
            $validated['pickup_time'] = now();
            $delivery->order()->update(['order_status' => 'out_for_delivery']);

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
        }

        if ($status === 'delivered') {
            $validated['delivered_time'] = now();
            $delivery->order()->update(['order_status' => 'completed', 'payment_status' => 'paid']);
            if ($delivery->driver_id) {
                Driver::where('id', $delivery->driver_id)->update(['status' => 'available']);
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
        } elseif ($status === 'failed' && $delivery->driver_id) {
            Driver::where('id', $delivery->driver_id)->update(['status' => 'available']);
        }

        if (isset($validated['driver_id']) && $validated['driver_id'] !== $delivery->driver_id) {
            // Mark new driver as on delivery
            Driver::where('id', $validated['driver_id'])->update(['status' => 'on_delivery']);
            $delivery->order()->update(['assigned_driver_id' => $validated['driver_id']]);
        }

        $delivery->update($validated);

        return response()->json($delivery->load(['order', 'driver']));
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
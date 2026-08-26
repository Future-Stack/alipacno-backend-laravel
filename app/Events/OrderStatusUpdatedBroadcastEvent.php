<?php

namespace App\Events;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $action = 'status_updated'
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('branch.' . $this->order->branch_id . '.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status_updated';
    }

    public function broadcastWith(): array
    {
        $order = $this->order->fresh([
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
            'kitchenOrders',
        ]);

        return [
            'action' => $this->action,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total' => (float) $order->total,
            'formatted_total' => '£' . number_format((float) $order->total, 2),
            'customer_name' => $order->customer_name ?? $order->user?->name ?? 'Guest Customer',
            'customer_phone' => $order->customer_phone ?? $order->user?->phone,
            'delivery_address' => $order->delivery_address,
            'notes' => $order->notes,
            'created_at' => $order->created_at?->format('h:i A'),
            'created_at_iso' => $order->created_at?->toIso8601String(),
            'estimated_delivery_time' => $order->estimated_delivery_time,
            'assigned_driver' => $order->assignedDriver ? [
                'id' => $order->assignedDriver->id,
                'name' => $order->assignedDriver->name,
                'phone' => $order->assignedDriver->phone,
                'vehicle_type' => $order->assignedDriver->vehicle_type,
            ] : null,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->menuItem?->name ?? 'Item',
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'size' => $item->size?->name,
                    'cooking_preference' => $item->cookingPreference?->name,
                    'spice_level' => $item->spiceLevel?->name,
                    'toppings' => $item->toppings->map(fn($t) => $t->topping?->name)->filter()->values(),
                ];
            }),
            'order' => $order,
        ];
    }
}

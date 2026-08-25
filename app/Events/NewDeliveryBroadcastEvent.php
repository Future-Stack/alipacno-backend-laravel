<?php

namespace App\Events;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDeliveryBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('branch.' . $this->order->branch_id . '.drivers'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.new_request';
    }

    public function broadcastWith(): array
    {
        $order = $this->order->loadMissing(['items.menuItem', 'branch', 'address']);
        $firstItem = $order->items->first();
        $menuItem = $firstItem?->menuItem;
        $itemsCount = $order->items->count();

        $earnings = (float) $order->delivery_fee;

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'category_tag' => $menuItem?->category?->name ?? 'Special',
            'title' => $menuItem ? $menuItem->name . ($itemsCount > 1 ? " + " . ($itemsCount - 1) . " more" : "") : 'Delivery Package',
            'image_url' => $menuItem?->image_url,
            'earnings' => $earnings,
            'formatted_earnings' => '£' . number_format($earnings, 2),
            'delivery_fee' => (float) $order->delivery_fee,
            'formatted_delivery_fee' => $order->delivery_fee > 0 ? '£' . number_format((float) $order->delivery_fee, 2) : 'Free',
            'rider_tip' => (float) $order->rider_tip,
            'formatted_rider_tip' => '£' . number_format((float) $order->rider_tip, 2),
            'total_order_amount' => (float) $order->total,
            'formatted_total_amount' => '£' . number_format((float) $order->total, 2),
            'pickup_location' => $order->branch ? ($order->branch->address ?? $order->branch->name) : 'Pacinos Branch',
            'delivery_location' => $order->delivery_address ?? ($order->address ? $order->address->address_line_1 . ', ' . $order->address->postcode : 'Customer Location'),
            'distance_km' => $order->calculateDistanceKm(),
            'distance_remaining' => $order->calculateDistanceKm() . ' km Remaining',
            'time_remaining_minutes' => $order->calculateRemainingMinutes(),
            'time_remaining' => $order->calculateRemainingMinutes() . ' mins Remaining',
            'delivery_time_formatted' => $order->estimated_delivery_time ? Carbon::parse($order->estimated_delivery_time)->format('l, M d, h:i A') : $order->created_at->format('l, M d, h:i A'),
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->menuItem?->name ?? 'Item',
                    'quantity' => $item->quantity,
                    'price' => (float) $item->unit_price,
                    'image_url' => $item->menuItem?->image_url,
                ];
            }),
        ];
    }
}

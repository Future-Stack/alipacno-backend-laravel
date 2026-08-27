<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdatedBroadcastEvent;
use App\Models\KitchenOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KitchenOrderController extends Controller
{
    /**
     * Display a listing of kitchen orders (KDS).
     */
    public function index(Request $request)
    {
        $query = KitchenOrder::with([
            'order.items.menuItem',
            'order.items.size',
            'order.items.cookingPreference',
            'order.items.spiceLevel',
            'order.items.toppings',
            'kitchenStation',
        ]);

        if ($request->filled('kitchen_station_id')) {
            $query->where('kitchen_station_id', $request->kitchen_station_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['pending', 'preparing', 'ready']);
        }

        $authUser = $request->user() ?? auth('sanctum')->user();

        if ($request->filled('branch_id')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        } elseif ($authUser && $authUser->hasRole(['branch_admin', 'cashier', 'chef', 'waiter', 'staff', 'Branch Manager', 'Chef'])) {
            $userBranchId = $authUser->branch_id 
                ?? \App\Models\BranchAdmin::where('email', $authUser->email)->value('branch_id')
                ?? \App\Models\Staff::where('email', $authUser->email)->value('branch_id');
            if ($userBranchId) {
                $query->whereHas('order', function ($q) use ($userBranchId) {
                    $q->where('branch_id', $userBranchId);
                });
            }
        }

        $query->orderBy('created_at', 'asc');

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 20)));
    }

    /**
     * Store a new kitchen order ticket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'status' => 'nullable|in:pending,preparing,ready,served',
        ]);

        $kitchenOrder = KitchenOrder::create($validated);

        return response()->json($kitchenOrder->load([
            'order.items.menuItem',
            'order.items.size',
            'order.items.cookingPreference',
            'order.items.spiceLevel',
        ]), 201);
    }

    /**
     * Display the specified kitchen order ticket.
     */
    public function show(KitchenOrder $kitchenOrder)
    {
        return response()->json($kitchenOrder->load([
            'order.items.menuItem',
            'order.items.size',
            'order.items.cookingPreference',
            'order.items.spiceLevel',
            'kitchenStation',
        ]));
    }

    /**
     * Update kitchen order status (KDS progress).
     */
    public function update(Request $request, KitchenOrder $kitchenOrder)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,preparing,ready,served',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
        ]);

        if ($validated['status'] === 'preparing' && !$kitchenOrder->started_at) {
            $validated['started_at'] = now();
            $kitchenOrder->order()->update(['order_status' => 'preparing']);
        } elseif ($validated['status'] === 'ready') {
            $validated['completed_at'] = now();
            $kitchenOrder->order()->update(['order_status' => 'ready']);
        }

        $kitchenOrder->update($validated);

        // Broadcast real-time order update to Branch Kanban Board
        if ($kitchenOrder->order && $kitchenOrder->order->branch_id) {
            try {
                broadcast(new OrderStatusUpdatedBroadcastEvent($kitchenOrder->order->fresh(), 'kitchen_status_updated'));
            } catch (\Exception $e) {
                Log::error('Kitchen Order Status Broadcast Error: ' . $e->getMessage());
            }
        }

        return response()->json($kitchenOrder->load([
            'order.items.menuItem',
            'order.items.size',
            'order.items.cookingPreference',
            'order.items.spiceLevel',
        ]));
    }

    /**
     * Remove kitchen order.
     */
    public function destroy(KitchenOrder $kitchenOrder)
    {
        $kitchenOrder->delete();

        return response()->json(null, 204);
    }
}
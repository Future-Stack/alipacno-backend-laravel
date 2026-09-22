<?php

namespace App\Http\Controllers;

use App\Models\CookingPreference;
use App\Models\ItemSize;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use App\Models\SpiceLevel;
use App\Models\Topping;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Display a listing of order line items.
     */
    public function index(Request $request)
    {
        $query = OrderItem::with(['order', 'menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping']);

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('menu_item_id')) {
            $query->where('menu_item_id', $request->menu_item_id);
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created order line item & update parent order total.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'menu_item_id' => 'required|exists:menu_items,id',
            'size_id' => 'nullable|exists:item_sizes,id',
            'cooking_preference_id' => 'nullable|exists:cooking_preferences,id',
            'spice_level_id' => 'nullable|exists:spice_levels,id',
            'topping_ids' => 'nullable|array',
            'topping_ids.*' => 'exists:toppings,id',
            'quantity' => 'required|integer|min:1',
            'special_instructions' => 'nullable|string',
        ]);

        $order = Order::find($validated['order_id']);

        // Validate branch stock before adding line item
        if ($order && !empty($order->branch_id)) {
            InventoryStockService::validateStockForItems((int) $order->branch_id, [
                ['menu_item_id' => $validated['menu_item_id'], 'quantity' => (int) $validated['quantity']]
            ]);
        }

        $menuItem = MenuItem::findOrFail($validated['menu_item_id']);
        $unitPrice = (float) $menuItem->price;

        $sizeName = null;
        if (!empty($validated['size_id'])) {
            $size = ItemSize::find($validated['size_id']);
            if ($size) {
                $sizeName = $size->name;
                $unitPrice += (float) $size->extra_price;
            }
        }

        $prefName = null;
        if (!empty($validated['cooking_preference_id'])) {
            $pref = CookingPreference::find($validated['cooking_preference_id']);
            $prefName = $pref?->name;
        }

        $spiceName = null;
        if (!empty($validated['spice_level_id'])) {
            $spice = SpiceLevel::find($validated['spice_level_id']);
            $spiceName = $spice?->name;
        }

        // Compute toppings price
        $toppingTotal = 0.00;
        $toppingsToInsert = [];
        if (!empty($validated['topping_ids'])) {
            $toppings = Topping::whereIn('id', $validated['topping_ids'])->get();
            foreach ($toppings as $top) {
                $toppingTotal += (float) $top->price;
                $toppingsToInsert[] = [
                    'topping_id' => $top->id,
                    'topping_name' => $top->name,
                    'price' => $top->price,
                ];
            }
        }

        $finalUnitPrice = $unitPrice + $toppingTotal;
        $quantity = (int) $validated['quantity'];
        $subtotal = $finalUnitPrice * $quantity;

        $orderItem = OrderItem::create([
            'order_id' => $validated['order_id'],
            'menu_item_id' => $validated['menu_item_id'],
            'item_name' => $menuItem->name,
            'size_id' => $validated['size_id'] ?? null,
            'size_name' => $sizeName,
            'cooking_preference_id' => $validated['cooking_preference_id'] ?? null,
            'cooking_preference' => $prefName,
            'spice_level_id' => $validated['spice_level_id'] ?? null,
            'spice_level' => $spiceName,
            'quantity' => $quantity,
            'unit_price' => $finalUnitPrice,
            'subtotal' => $subtotal,
            'special_instructions' => $validated['special_instructions'] ?? null,
        ]);

        foreach ($toppingsToInsert as $topData) {
            OrderItemTopping::create(array_merge($topData, ['order_item_id' => $orderItem->id]));
        }

        // Recalculate parent order total amount
        if ($order) {
            $newTotal = OrderItem::where('order_id', $order->id)->sum('subtotal');
            $order->update([
                'subtotal' => $newTotal,
                'total' => max(0, $newTotal + $order->delivery_fee + $order->vat + $order->tip + $order->rider_tip - $order->discount)
            ]);
        }

        return response()->json($orderItem->load(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings']), 201);
    }

    /**
     * Display the specified order line item.
     */
    public function show(OrderItem $orderItem)
    {
        return response()->json($orderItem->load(['order', 'menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings.topping']));
    }

    /**
     * Update the specified order line item.
     */
    public function update(Request $request, OrderItem $orderItem)
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'special_instructions' => 'nullable|string',
        ]);

        if (isset($validated['quantity'])) {
            $order = Order::find($orderItem->order_id);
            if ($order && !empty($order->branch_id)) {
                InventoryStockService::validateStockForItems((int) $order->branch_id, [
                    ['menu_item_id' => $orderItem->menu_item_id, 'quantity' => (int) $validated['quantity']]
                ]);
            }

            $validated['subtotal'] = $orderItem->unit_price * (int) $validated['quantity'];
        }

        $orderItem->update($validated);

        // Recalculate parent order total
        $order = Order::find($orderItem->order_id);
        if ($order) {
            $newTotal = OrderItem::where('order_id', $order->id)->sum('subtotal');
            $order->update([
                'subtotal' => $newTotal,
                'total' => max(0, $newTotal + $order->delivery_fee + $order->vat + $order->tip + $order->rider_tip - $order->discount)
            ]);
        }

        return response()->json($orderItem->load(['menuItem', 'size', 'cookingPreference', 'spiceLevel', 'toppings']));
    }

    /**
     * Remove the specified order line item & update parent order total.
     */
    public function destroy(OrderItem $orderItem)
    {
        $orderId = $orderItem->order_id;
        $orderItem->delete();

        $order = Order::find($orderId);
        if ($order) {
            $newTotal = OrderItem::where('order_id', $order->id)->sum('subtotal');
            $order->update([
                'subtotal' => $newTotal,
                'total_amount' => max(0, $newTotal + $order->delivery_fee + $order->service_fee + $order->rider_tip - $order->discount_amount)
            ]);
        }

        return response()->json(null, 204);
    }
}
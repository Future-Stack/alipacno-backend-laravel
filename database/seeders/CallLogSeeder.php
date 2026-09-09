<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CallLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::first() ?? Branch::create([
            'restaurant_id' => Restaurant::value('id') ?? 1,
            'name' => 'Pacinos HQ (Woodstock)',
            'address' => '7 Elm Street, Woodstock, OX7 1ER',
            'city' => 'Woodstock',
            'postal_code' => 'NW1 6XE',
            'phone' => '+44 3050 244898',
            'email' => 'hq@alipacino.com',
            'status' => 'active',
        ]);

        $staff = Staff::first();
        $user = User::where('user_type', 'customer')->first();

        // Sample Orders
        $order1 = Order::firstOrCreate(
            ['order_number' => '#44569'],
            [
                'restaurant_id' => $branch->restaurant_id,
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'customer_name' => 'Sarah Mitchell',
                'customer_phone' => '+44 3050 244898',
                'order_type' => 'delivery',
                'order_status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'order_source' => 'call',
                'total' => 300.00,
                'subtotal' => 280.00,
                'delivery_fee' => 20.00,
                'delivery_address' => 'Flat 4B, 12 Gloucester Place, London, NW1 6XE',
            ]
        );

        $order2 = Order::firstOrCreate(
            ['order_number' => '#UK1042'],
            [
                'restaurant_id' => $branch->restaurant_id,
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'customer_name' => 'Sarah Mitchell',
                'customer_phone' => '+44 3050 244898',
                'order_type' => 'delivery',
                'order_status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'order_source' => 'call',
                'total' => 300.00,
                'subtotal' => 285.00,
                'delivery_fee' => 15.00,
                'delivery_address' => 'Flat 4B, 12 Gloucester Place, London, NW1 6XE',
            ]
        );

        $order3 = Order::firstOrCreate(
            ['order_number' => '#4569'],
            [
                'restaurant_id' => $branch->restaurant_id,
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'customer_name' => 'Brooklyn Simmons',
                'customer_phone' => '(312) 555-0192',
                'order_type' => 'delivery',
                'order_status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'order_source' => 'call',
                'total' => 300.00,
                'subtotal' => 290.00,
                'delivery_fee' => 10.00,
                'delivery_address' => '14 Court Yard, Eltham, London, EL01',
            ]
        );

        // Seed Order Items for Orders
        $menuItems = \App\Models\MenuItem::take(3)->get();
        $menuItem1 = $menuItems->first();
        $menuItem2 = $menuItems->count() > 1 ? $menuItems[1] : $menuItem1;

        foreach ([$order1, $order2, $order3] as $order) {
            if ($order->items()->count() === 0) {
                if ($menuItem1) {
                    \App\Models\OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $menuItem1->id,
                        'item_name' => $menuItem1->name,
                        'quantity' => 2,
                        'unit_price' => $menuItem1->price ?? 39.99,
                        'subtotal' => ($menuItem1->price ?? 39.99) * 2,
                        'special_instructions' => 'Extra sauce, please.',
                    ]);
                }
                if ($menuItem2) {
                    \App\Models\OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $menuItem2->id,
                        'item_name' => $menuItem2->name,
                        'quantity' => 1,
                        'unit_price' => $menuItem2->price ?? 39.99,
                        'subtotal' => $menuItem2->price ?? 39.99,
                        'special_instructions' => 'Medium well done.',
                    ]);
                }
            }
        }
        $demoLogs = [
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => $order2->id,
                'customer_name' => 'Sarah Mitchell',
                'phone' => '+44 3050 244898',
                'postcode' => 'NW1 6XE',
                'call_type' => 'incoming',
                'call_status' => 'answered',
                'call_duration' => 252, // 04:12
                'call_outcome' => 'converted',
                'notes' => 'Customer ordered party meal combo and drinks.',
                'started_at' => Carbon::now()->subMinutes(15),
                'ended_at' => Carbon::now()->subMinutes(15)->addSeconds(252),
            ],
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => $order3->id,
                'customer_name' => 'Sarah Mitchell',
                'phone' => '+44 3050 244898',
                'postcode' => 'NW1 6XE',
                'call_type' => 'incoming',
                'call_status' => 'missed',
                'call_duration' => 0,
                'call_outcome' => 'callback',
                'notes' => 'Missed call during peak rush hour.',
                'started_at' => Carbon::now()->subMinutes(45),
                'ended_at' => Carbon::now()->subMinutes(45),
            ],
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => $order1->id,
                'customer_name' => 'Sarah Mitchell',
                'phone' => '+44 3050 244898',
                'postcode' => 'NW1 6XE',
                'call_type' => 'incoming',
                'call_status' => 'answered',
                'call_duration' => 252, // 04:12
                'call_outcome' => 'converted',
                'notes' => 'Customer inquired about specials and placed order #44569.',
                'started_at' => Carbon::now()->subHours(2),
                'ended_at' => Carbon::now()->subHours(2)->addSeconds(252),
            ],
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => $order3->id,
                'customer_name' => 'Sarah Mitchell',
                'phone' => '+44 3050 244886',
                'postcode' => 'NW1 6XE',
                'call_type' => 'incoming',
                'call_status' => 'missed',
                'call_duration' => 0,
                'call_outcome' => 'callback',
                'notes' => 'Callback needed for catering query.',
                'started_at' => Carbon::now()->subHours(3),
                'ended_at' => Carbon::now()->subHours(3),
            ],
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => null,
                'customer_name' => 'Sarah Mitchell',
                'phone' => '+44 3050 244898',
                'postcode' => 'NW1 6XE',
                'call_type' => 'incoming',
                'call_status' => 'answered',
                'call_duration' => 252, // 04:12
                'call_outcome' => 'no_order',
                'notes' => 'Table reservation inquiry.',
                'started_at' => Carbon::now()->subHours(5),
                'ended_at' => Carbon::now()->subHours(5)->addSeconds(252),
            ],
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => $order3->id,
                'customer_name' => 'Brooklyn Simmons',
                'phone' => '(312) 555-0192',
                'postcode' => 'EL01',
                'call_type' => 'incoming',
                'call_status' => 'answered',
                'call_duration' => 138, // 02:18
                'call_outcome' => 'converted',
                'notes' => 'Order placed directly via customer support call.',
                'started_at' => Carbon::now()->subHours(6),
                'ended_at' => Carbon::now()->subHours(6)->addSeconds(138),
            ],
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => $order1->id,
                'customer_name' => 'Brooklyn Simmons',
                'phone' => '(312) 555-0192',
                'postcode' => 'EL01',
                'call_type' => 'incoming',
                'call_status' => 'answered',
                'call_duration' => 138, // 02:18
                'call_outcome' => 'converted',
                'notes' => 'Repeat customer order for Eltham branch.',
                'started_at' => Carbon::now()->subDays(1),
                'ended_at' => Carbon::now()->subDays(1)->addSeconds(138),
            ],
            [
                'branch_id' => $branch->id,
                'user_id' => $user?->id,
                'staff_id' => $staff?->id,
                'order_id' => null,
                'customer_name' => 'Brooklyn Simmons',
                'phone' => '(312) 555-0192',
                'postcode' => 'EL01',
                'call_type' => 'incoming',
                'call_status' => 'missed',
                'call_duration' => 0,
                'call_outcome' => 'callback',
                'notes' => 'Customer called outside opening hours.',
                'started_at' => Carbon::now()->subDays(2),
                'ended_at' => Carbon::now()->subDays(2),
            ],
        ];

        foreach ($demoLogs as $logData) {
            CallLog::create($logData);
        }
    }
}

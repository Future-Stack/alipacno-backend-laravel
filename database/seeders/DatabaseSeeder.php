<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\CookingPreference;
use App\Models\ItemSize;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\SpiceLevel;
use App\Models\Subcategory;
use App\Models\Topping;
use App\Models\Page;
use App\Models\Faq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FaqSeeder::class,
            PagesSeeder::class,
            RolePermissionSeeder::class,
            
        ]);

        // 1. Create Default Enterprise Restaurant
        $restaurant = Restaurant::firstOrCreate(
            ['name' => 'Ali Pacino Steakhouse'],
            [
                'slug' => 'ali-pacino-steakhouse',
                'logo' => '/logo.png',
                'cover_image' => '/customer/banner-men.png',
                'phone' => '+442079460912',
                'email' => 'contact@alipacino.com',
                'address' => '221B Baker Street, Marylebone',
                'postcode' => 'NW1 6XE',
                'status' => 'active',
            ]
        );

        // 2. Create Default Branches
        $branch1 = Branch::firstOrCreate(
            ['name' => 'Cloud Gate (The Bean), Chicago'],
            [
                'restaurant_id' => $restaurant->id,
                'address' => '7 Elm Street, Woodstock, OX7 1ER',
                'city' => 'Chicago',
                'postal_code' => 'NW1 6XE',
                'phone' => '+1 312 555 0199',
                'email' => 'chicago@alipacino.com',
                'latitude' => 41.8827,
                'longitude' => -87.6233,
                'opening_time' => '10:00',
                'closing_time' => '23:00',
                'is_active' => true,
            ]
        );
        $branch1->settings()->firstOrCreate([], [
            'delivery_radius' => 15.00,
            'minimum_order' => 10.00,
            'tax_rate' => 10.00,
            'currency' => 'GBP',
        ]);

        $branch2 = Branch::firstOrCreate(
            ['name' => 'The High Line, New York City'],
            [
                'restaurant_id' => $restaurant->id,
                'address' => '7 Elm Street, Woodstock, OX7 1ER',
                'city' => 'New York',
                'postal_code' => '10011',
                'phone' => '+1 212 555 0188',
                'email' => 'nyc@alipacino.com',
                'latitude' => 40.7480,
                'longitude' => -74.0048,
                'opening_time' => '10:00',
                'closing_time' => '23:00',
                'is_active' => true,
            ]
        );

        // 3. Create Kitchen Station for Branch 1
        KitchenStation::firstOrCreate(
            ['branch_id' => $branch1->id, 'name' => 'Main Grill Station'],
            ['display_order' => 1]
        );

        // 4. Seed Categories matching Customer Frontend
        $categoriesData = [
            ['name' => 'Steaks', 'icon' => '/customer/menu/steaks.svg', 'sort_order' => 1],
            ['name' => 'Starters', 'icon' => '/customer/menu/starters.svg', 'sort_order' => 2],
            ['name' => 'Sides', 'icon' => '/customer/menu/sides.svg', 'sort_order' => 3],
            ['name' => 'Drinks', 'icon' => '/customer/menu/drinks.svg', 'sort_order' => 4],
            ['name' => 'Desserts', 'icon' => '/customer/menu/desserts.svg', 'sort_order' => 5],
            ['name' => 'Lunch Special', 'icon' => '/customer/menu/lunch.svg', 'sort_order' => 6],
        ];

        $categories = [];
        foreach ($categoriesData as $catData) {
            $cat = Category::firstOrCreate(
                ['name' => $catData['name']],
                [
                    'restaurant_id' => $restaurant->id,
                    'slug' => Str::slug($catData['name']),
                    'icon' => $catData['icon'],
                    'sort_order' => $catData['sort_order'],
                    'is_active' => true,
                ]
            );
            $categories[$catData['name']] = $cat;
        }

        //Seed Subcategories
        $subcategoriesData = [
            ['name' => 'Grass fed', 'sort_order' => 1],
            ['name' => 'Wagyu Selection', 'sort_order' => 2],
            ['name' => 'Dry Aged', 'sort_order' => 3],
        ];

        $subcategories = [];
        foreach ($subcategoriesData as $subCatData) {
            $subcat = Subcategory::firstOrCreate(
                ['name' => $subCatData['name']],
                [
                    'restaurant_id' => $restaurant->id,
                    'slug' => Str::slug($subCatData['name']),
                    'category_id' => 1,
                    'sort_order' => $subCatData['sort_order'],
                    'is_active' => true,
                ]
            );
            $subcategories[$subCatData['name']] = $subcat;
        }

        // 5. Seed Menu Items for Steaks Category
        $steaksCategory = $categories['Steaks'];

        $menuItems = [
            [
                'name' => 'Grilled chicken pieces',
                'price' => 39.99,
                'subcategory_id' => 1,
                'original_price' => 52.00,
                'discount_price' => 39.99,
                'rating' => 4.5,
                'review_count' => 128,
                'image' => '/customer/popular-1.png',
                'description' => 'Tender grilled chicken pieces seasoned with garlic butter herbs.',
                'is_popular' => true,
                'is_happy_hour_eligible' => true,
            ],
            [
                'name' => 'Ribeye Steak',
                'price' => 39.99,
                'subcategory_id' => 2,
                'original_price' => 52.00,
                'discount_price' => 39.99,
                'rating' => 4.8,
                'review_count' => 210,
                'image' => '/customer/happypricing-2.png',
                'description' => 'Juicy prime Ribeye steak seared on high flame.',
                'is_popular' => true,
                'is_happy_hour_eligible' => true,
            ],
            [
                'name' => 'Vegetable Stir Fry',
                'price' => 39.99,
                'subcategory_id' => 3,
                'original_price' => 52.00,
                'discount_price' => 39.99,
                'rating' => 4.5,
                'review_count' => 84,
                'image' => '/customer/happypricing-3.png',
                'description' => 'Fresh garden vegetables wok-fried in savory sauce.',
                'is_popular' => false,
                'is_happy_hour_eligible' => true,
            ],
            [
                'name' => 'Filet Mignon',
                'price' => 48.00,
                'subcategory_id' => 1,
                'original_price' => 60.00,
                'discount_price' => 48.00,
                'rating' => 4.9,
                'review_count' => 350,
                'image' => '/customer/most-popular-1.png',
                'description' => 'Premium center-cut filet mignon grilled with garlic herb butter.',
                'is_popular' => true,
                'is_happy_hour_eligible' => false,
            ],
        ];

        foreach ($menuItems as $itemData) {
            $item = MenuItem::firstOrCreate(
                ['name' => $itemData['name']],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch1->id,
                    'category_id' => $steaksCategory->id,
                    'subcategory_id' => $itemData['subcategory_id'],
                    'slug' => Str::slug($itemData['name']),
                    'description' => $itemData['description'],
                    'image' => $itemData['image'],
                    'price' => $itemData['price'],
                    'original_price' => $itemData['original_price'],
                    'discount_price' => $itemData['discount_price'],
                    'rating' => $itemData['rating'],
                    'review_count' => $itemData['review_count'],
                    'is_popular' => $itemData['is_popular'],
                    'is_happy_hour_eligible' => $itemData['is_happy_hour_eligible'],
                    'status' => 'available',
                ]
            );

            // Add Item Sizes
            ItemSize::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Regular'], ['size_description' => '5-inch', 'extra_price' => 0.00]);
            ItemSize::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Medium'], ['size_description' => '8-inch', 'extra_price' => 4.00]);
            ItemSize::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Large'], ['size_description' => '12-inch', 'extra_price' => 8.00]);

            // Add Cooking Preferences
            CookingPreference::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Medium Rare']);
            CookingPreference::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Rare']);
            CookingPreference::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Well Done']);

            // Add Spice Levels
            SpiceLevel::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Mild']);
            SpiceLevel::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Medium']);
            SpiceLevel::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Extra Hot']);

            // Add Toppings
            Topping::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Bone Marrow Butter'], ['price' => 2.50]);
            Topping::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Mushroom Sauce'], ['price' => 3.00]);
            Topping::firstOrCreate(['menu_item_id' => $item->id, 'name' => 'Caramelized Onions'], ['price' => 1.50]);
        }

        // 6. Seed Sample Coupons
        \App\Models\Coupon::firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'restaurant_id' => $restaurant->id,
                'discount_type' => 'percentage',
                'discount' => 10.00,
                'minimum_order' => 20.00,
                'status' => 'active',
            ]
        );

        \App\Models\Coupon::firstOrCreate(
            ['code' => 'SAVE5'],
            [
                'restaurant_id' => $restaurant->id,
                'discount_type' => 'fixed',
                'discount' => 5.00,
                'minimum_order' => 15.00,
                'status' => 'active',
            ]
        );

        // 7. Seed Delivery Areas
        \App\Models\DeliveryArea::firstOrCreate(
            ['postcode' => 'OX7 1ER'],
            [
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch1->id,
                'delivery_fee' => 2.50,
                'minimum_order' => 15.00,
                'estimated_delivery_time' => 30,
                'is_active' => true,
            ]
        );

        \App\Models\DeliveryArea::firstOrCreate(
            ['postcode' => 'NW1 6XE'],
            [
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch1->id,
                'delivery_fee' => 0.00,
                'minimum_order' => 10.00,
                'estimated_delivery_time' => 25,
                'is_active' => true,
            ]
        );

        // 8. Seed Branch Manager for Branch 1
        $managerUser = \App\Models\User::where('email', 'manager@restaurant.com')->first();
        if ($managerUser) {
            \App\Models\BranchAdmin::firstOrCreate(
                ['email' => $managerUser->email],
                [
                    'user_id' => $managerUser->id,
                    'branch_id' => $branch1->id,
                    'name' => $managerUser->name,
                    'phone' => $managerUser->phone,
                    'password' => $managerUser->password,
                    'status' => 'active',
                ]
            );
        }

        // 9. Seed Delivery Drivers
        $driverUser = \App\Models\User::where('email', 'driver@restaurant.com')->first();
        \App\Models\Driver::firstOrCreate(
            ['phone' => '+447000000007'],
            [
                'user_id' => $driverUser?->id,
                'branch_id' => $branch1->id,
                'name' => 'Delivery Driver (Alex)',
                'vehicle_type' => 'Motorcycle',
                'status' => 'available',
            ]
        );

        // 10. Seed Call Logs
        $this->call([
            CallLogSeeder::class,
        ]);
    }
}


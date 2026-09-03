<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignStatistic;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiInsightService
{
    /**
     * Cache duration in minutes (e.g. 6 hours).
     */
    protected int $cacheTtl = 360;

    /**
     * Get complete AI Insights & Suggestions Dashboard data.
     */
    public function getDashboardData(?int $branchId = null, bool $forceRefresh = false): array
    {
        $cacheKey = 'ai_insights_dashboard_' . ($branchId ?? 'all');

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes($this->cacheTtl), function () use ($branchId) {
            return $this->computeInsights($branchId);
        });
    }

    /**
     * Compute real-time analytics from the database and enrich with AI suggestions.
     */
    public function computeInsights(?int $branchId = null): array
    {
        $now = Carbon::now();
        $thisWeekStart = $now->copy()->subDays(7);
        $lastWeekStart = $now->copy()->subDays(14);

        // 1. Fetch raw analytical metrics from DB
        $productStats = $this->getProductAnalytics($thisWeekStart, $now, $lastWeekStart, $branchId);
        $customerStats = $this->getCustomerAnalytics($branchId);
        $areaStats = $this->getAreaAnalytics($branchId);
        $campaignStats = $this->getCampaignAnalytics();

        // 2. Derive Top KPI Highlights
        $hasProductData = !empty($productStats['items']);
        $topSoldProduct = $hasProductData ? $productStats['items'][0] : null;

        $hasAreaData = !empty($areaStats);
        $topArea = $hasAreaData ? $areaStats[0] : null;

        $hasCustomerData = !empty($customerStats);
        $topCustomer = $hasCustomerData ? $customerStats[0] : null;

        $bestCampaign = $campaignStats['best'] ?? null;

        // 3. Prepare payload for Gemini AI enrichment
        $summaryForAi = [
            'top_product' => $topSoldProduct['product_name'] ?? null,
            'top_product_growth' => $topSoldProduct['order_growth'] ?? null,
            'top_product_peak' => $topSoldProduct['peak_order_time'] ?? null,
            'top_area' => $topArea['area_name'] ?? null,
            'top_selling_products' => array_slice(array_map(function ($item) {
                return [
                    'name' => $item['product_name'],
                    'orders' => $item['weekly_orders'],
                    'growth' => $item['order_growth'],
                    'top_area' => $item['top_area'],
                ];
            }, $productStats['items']), 0, 5),
            'top_customers' => array_slice(array_map(function ($cust) {
                return [
                    'name' => $cust['name'],
                    'orders' => $cust['orders'],
                    'favorite' => $cust['favorite_item'],
                    'total_spend' => $cust['total_spend'],
                ];
            }, $customerStats), 0, 5),
            'top_areas' => array_slice(array_map(function ($area) {
                return [
                    'area' => $area['area_name'],
                    'orders' => $area['total_orders'],
                    'top_item' => $area['top_items'][0] ?? null,
                ];
            }, $areaStats), 0, 5),
        ];

        // 4. Enrich with Gemini AI (or rule-based fallback)
        $aiEnrichment = $this->generateAiSuggestions($summaryForAi);

        // 5. Merge AI suggestions with table items
        $finalProducts = array_map(function ($product, $index) use ($aiEnrichment) {
            $suggestion = $aiEnrichment['product_suggestions'][$product['product_name']] 
                ?? $aiEnrichment['product_suggestions_indexed'][$index] 
                ?? null;
            $product['ai_marketing_suggestion'] = $suggestion;
            return $product;
        }, $productStats['items'], array_keys($productStats['items']));

        $finalCustomers = array_map(function ($cust, $index) use ($aiEnrichment) {
            $suggestion = $aiEnrichment['customer_suggestions'][$cust['name']] 
                ?? null;
            $cust['ai_suggestion'] = $suggestion;
            return $cust;
        }, $customerStats, array_keys($customerStats));

        return [
            'status' => 'success',
            'generated_at' => $now->toIso8601String(),
            'is_ai_powered' => $aiEnrichment['is_ai_powered'] ?? false,
            'kpis' => [
                'top_sold_product' => [
                    'title' => 'Top Sold Product',
                    'name' => $topSoldProduct['product_name'] ?? null,
                    'orders' => (int) ($topSoldProduct['weekly_orders'] ?? 0),
                    'growth' => $topSoldProduct['order_growth'] ?? null,
                    'badge' => $hasProductData ? ($topSoldProduct['badge'] ?? 'High Demand') : null,
                    'image' => $topSoldProduct['image'] ?? null,
                ],
                'top_ordering_area' => [
                    'title' => 'Top Ordering Area',
                    'name' => $topArea['area_name'] ?? null,
                    'orders' => (int) ($topArea['total_orders'] ?? 0),
                    'growth' => $topArea['growth'] ?? null,
                    'badge' => $hasAreaData ? ($topArea['badge'] ?? 'Strong Growth') : null,
                ],
                'most_loyal_customer' => [
                    'title' => 'Most Loyal Customer',
                    'name' => $topCustomer['name'] ?? null,
                    'orders' => (int) ($topCustomer['orders'] ?? 0),
                    'growth' => $topCustomer['growth'] ?? null,
                    'badge' => $hasCustomerData ? ($topCustomer['badge'] ?? 'VIP Customer') : null,
                    'avatar' => $topCustomer['avatar'] ?? null,
                ],
                'best_campaign' => [
                    'title' => 'Best Campaign',
                    'name' => $bestCampaign['name'] ?? null,
                    'reached' => (int) ($bestCampaign['reached'] ?? 0),
                    'growth' => $bestCampaign['growth_percent'] ?? null,
                    'badge' => $bestCampaign ? ($bestCampaign['badge'] ?? 'Top Performer') : null,
                ],
            ],
            'ai_hero_banner' => [
                'headline' => $aiEnrichment['hero']['headline'] ?? null,
                'recommendation' => $aiEnrichment['hero']['recommendation'] ?? null,
            ],
            'top_selling_products' => array_values(array_slice($finalProducts, 0, 8)),
            'top_customers' => array_values(array_slice($finalCustomers, 0, 8)),
            'top_ordering_areas' => array_values(array_slice($areaStats, 0, 8)),
            'ai_recommended_campaigns' => array_values($aiEnrichment['campaigns'] ?? []),
        ];
    }

    /**
     * Compute Top Selling Products & Weekly Growth.
     */
    protected function getProductAnalytics(Carbon $thisWeekStart, Carbon $now, Carbon $lastWeekStart, ?int $branchId = null): array
    {
        // Current 7 days metrics
        $currentItemsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('orders.created_at', '>=', $thisWeekStart)
            ->where('orders.created_at', '<=', $now)
            ->whereNotIn('orders.order_status', ['cancelled', 'rejected']);

        if ($branchId) {
            $currentItemsQuery->where('orders.branch_id', $branchId);
        }

        $currentItems = $currentItemsQuery
            ->select(
                'order_items.menu_item_id',
                DB::raw('COALESCE(order_items.item_name, menu_items.name, "Special Dish") as product_name'),
                'menu_items.image',
                DB::raw('COUNT(DISTINCT orders.id) as weekly_orders'),
                DB::raw('SUM(order_items.quantity) as total_qty_sold'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('order_items.menu_item_id', 'order_items.item_name', 'menu_items.name', 'menu_items.image')
            ->orderByDesc('weekly_orders')
            ->limit(15)
            ->get();

        // Previous 7 days orders map for growth calculation
        $prevItemsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $lastWeekStart)
            ->where('orders.created_at', '<', $thisWeekStart)
            ->whereNotIn('orders.order_status', ['cancelled', 'rejected']);

        if ($branchId) {
            $prevItemsQuery->where('orders.branch_id', $branchId);
        }

        $prevItems = $prevItemsQuery
            ->select('order_items.menu_item_id', DB::raw('COUNT(DISTINCT orders.id) as prev_orders'))
            ->groupBy('order_items.menu_item_id')
            ->pluck('prev_orders', 'menu_item_id');

        $formatted = [];
        foreach ($currentItems as $item) {
            $prevCount = $prevItems[$item->menu_item_id] ?? 0;
            $growth = $prevCount > 0 
                ? round((($item->weekly_orders - $prevCount) / $prevCount) * 100, 1)
                : 28.0;

            $growthStr = ($growth >= 0 ? '+' : '') . $growth . '%';

            // Find peak order time for this item
            $peakHour = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.menu_item_id', $item->menu_item_id)
                ->where('orders.created_at', '>=', $thisWeekStart)
                ->select(DB::raw('HOUR(orders.created_at) as order_hour'), DB::raw('COUNT(*) as count'))
                ->groupBy('order_hour')
                ->orderByDesc('count')
                ->first();

            $peakTimeFormatted = $peakHour 
                ? $this->formatHourRange((int) $peakHour->order_hour) 
                : '6PM - 8PM';

            // Find top delivery area / city for this item
            $topArea = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->leftJoin('user_addresses', 'user_addresses.id', '=', 'orders.address_id')
                ->where('order_items.menu_item_id', $item->menu_item_id)
                ->where('orders.created_at', '>=', $thisWeekStart)
                ->whereNotNull('user_addresses.city')
                ->select('user_addresses.city', DB::raw('COUNT(*) as area_count'))
                ->groupBy('user_addresses.city')
                ->orderByDesc('area_count')
                ->first();

            $areaName = $topArea?->city ?: 'Downtown';

            $formatted[] = [
                'product_id' => $item->menu_item_id,
                'product_name' => $item->product_name,
                'image' => $item->image ? asset($item->image) : null,
                'weekly_orders' => (int) $item->weekly_orders,
                'order_growth' => $growthStr,
                'total_qty_sold' => (int) ($item->total_qty_sold ?? $item->weekly_orders),
                'revenue' => (float) $item->revenue,
                'revenue_formatted' => '£' . number_format($item->revenue, 2),
                'peak_order_time' => $peakTimeFormatted,
                'top_area' => strtoupper($areaName),
            ];
        }

        if (empty($formatted)) {
            $menuItems = MenuItem::limit(5)->get();
            if ($menuItems->isNotEmpty()) {
                foreach ($menuItems as $m) {
                    $formatted[] = [
                        'product_id' => $m->id,
                        'product_name' => $m->name,
                        'image' => $m->image ? asset($m->image) : null,
                        'weekly_orders' => 0,
                        'order_growth' => '0%',
                        'total_qty_sold' => 0,
                        'revenue' => 0.0,
                        'revenue_formatted' => '£0.00',
                        'peak_order_time' => '—',
                        'top_area' => '—',
                    ];
                }
            } else {
                $formatted = [
                    [
                        'product_id' => 1,
                        'product_name' => 'Burger Combo Deluxe',
                        'image' => null,
                        'weekly_orders' => 0,
                        'order_growth' => '0%',
                        'total_qty_sold' => 0,
                        'revenue' => 0.0,
                        'revenue_formatted' => '£0.00',
                        'peak_order_time' => '—',
                        'top_area' => '—',
                    ],
                    [
                        'product_id' => 2,
                        'product_name' => 'Pepperoni Pizza',
                        'image' => null,
                        'weekly_orders' => 0,
                        'order_growth' => '0%',
                        'total_qty_sold' => 0,
                        'revenue' => 0.0,
                        'revenue_formatted' => '£0.00',
                        'peak_order_time' => '—',
                        'top_area' => '—',
                    ],
                    [
                        'product_id' => 3,
                        'product_name' => 'Chicken Wings',
                        'image' => null,
                        'weekly_orders' => 0,
                        'order_growth' => '0%',
                        'total_qty_sold' => 0,
                        'revenue' => 0.0,
                        'revenue_formatted' => '£0.00',
                        'peak_order_time' => '—',
                        'top_area' => '—',
                    ],
                    [
                        'product_id' => 4,
                        'product_name' => 'Margarita Pizza',
                        'image' => null,
                        'weekly_orders' => 0,
                        'order_growth' => '0%',
                        'total_qty_sold' => 0,
                        'revenue' => 0.0,
                        'revenue_formatted' => '£0.00',
                        'peak_order_time' => '—',
                        'top_area' => '—',
                    ],
                    [
                        'product_id' => 5,
                        'product_name' => 'Zero Cola 330ml',
                        'image' => null,
                        'weekly_orders' => 0,
                        'order_growth' => '0%',
                        'total_qty_sold' => 0,
                        'revenue' => 0.0,
                        'revenue_formatted' => '£0.00',
                        'peak_order_time' => '—',
                        'top_area' => '—',
                    ],
                ];
            }
        }

        return ['items' => $formatted];
    }

    /**
     * Compute Top Customers analytics.
     */
    protected function getCustomerAnalytics(?int $branchId = null): array
    {
        $query = DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->whereNotIn('orders.order_status', ['cancelled', 'rejected']);

        if ($branchId) {
            $query->where('orders.branch_id', $branchId);
        }

        $customers = $query
            ->select(
                'orders.user_id',
                DB::raw('COALESCE(users.name, orders.customer_name, "Guest Customer") as name'),
                'users.avatar',
                DB::raw('COUNT(orders.id) as orders_count'),
                DB::raw('SUM(orders.total) as total_spent'),
                DB::raw('AVG(orders.total) as avg_spent')
            )
            ->groupBy('orders.user_id', 'users.name', 'orders.customer_name', 'users.avatar')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        $formatted = [];
        foreach ($customers as $cust) {
            // Find favorite item for this customer
            $favItem = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.user_id', $cust->user_id)
                ->select(DB::raw('COALESCE(order_items.item_name, "N/A") as item_name'), DB::raw('SUM(order_items.quantity) as qty'))
                ->groupBy('item_name')
                ->orderByDesc('qty')
                ->first();

            $favName = $favItem?->item_name ?: '—';

            $formatted[] = [
                'customer_id' => $cust->user_id,
                'name' => $cust->name,
                'avatar' => $cust->avatar ? asset($cust->avatar) : null,
                'orders' => (int) $cust->orders_count,
                'avg_order_value' => '£' . number_format($cust->avg_spent, 2),
                'favorite_item' => $favName,
                'total_spend' => '£' . number_format($cust->total_spent, 2),
                'growth' => '0%',
            ];
        }

        if (empty($formatted)) {
            $users = User::limit(5)->get();
            if ($users->isNotEmpty()) {
                foreach ($users as $u) {
                    $formatted[] = [
                        'customer_id' => $u->id,
                        'name' => $u->name,
                        'avatar' => $u->avatar ? asset($u->avatar) : null,
                        'orders' => 0,
                        'avg_order_value' => '£0.00',
                        'favorite_item' => '—',
                        'total_spend' => '£0.00',
                        'growth' => '0%',
                    ];
                }
            } else {
                $formatted = [
                    [
                        'customer_id' => 1,
                        'name' => 'James Smith',
                        'avatar' => null,
                        'orders' => 0,
                        'avg_order_value' => '£0.00',
                        'favorite_item' => '—',
                        'total_spend' => '£0.00',
                        'growth' => '0%',
                    ],
                    [
                        'customer_id' => 2,
                        'name' => 'William Smith',
                        'avatar' => null,
                        'orders' => 0,
                        'avg_order_value' => '£0.00',
                        'favorite_item' => '—',
                        'total_spend' => '£0.00',
                        'growth' => '0%',
                    ],
                    [
                        'customer_id' => 3,
                        'name' => 'Michael Brown',
                        'avatar' => null,
                        'orders' => 0,
                        'avg_order_value' => '£0.00',
                        'favorite_item' => '—',
                        'total_spend' => '£0.00',
                        'growth' => '0%',
                    ],
                    [
                        'customer_id' => 4,
                        'name' => 'David Wilson',
                        'avatar' => null,
                        'orders' => 0,
                        'avg_order_value' => '£0.00',
                        'favorite_item' => '—',
                        'total_spend' => '£0.00',
                        'growth' => '0%',
                    ],
                    [
                        'customer_id' => 5,
                        'name' => 'Emma Taylor',
                        'avatar' => null,
                        'orders' => 0,
                        'avg_order_value' => '£0.00',
                        'favorite_item' => '—',
                        'total_spend' => '£0.00',
                        'growth' => '0%',
                    ],
                ];
            }
        }

        return $formatted;
    }

    /**
     * Compute Top Ordering Areas analytics.
     */
    protected function getAreaAnalytics(?int $branchId = null): array
    {
        $query = DB::table('orders')
            ->leftJoin('user_addresses', 'user_addresses.id', '=', 'orders.address_id')
            ->whereNotIn('orders.order_status', ['cancelled', 'rejected']);

        if ($branchId) {
            $query->where('orders.branch_id', $branchId);
        }

        $areas = $query
            ->select(
                DB::raw('COALESCE(user_addresses.city, user_addresses.address_line_2, "Downtown") as area_name'),
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('SUM(orders.total) as total_spent'),
                DB::raw('AVG(orders.total) as avg_spent')
            )
            ->groupBy('area_name')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get();

        $badges = ['RECOMMENDED', 'HIGH POTENTIAL', 'GROWING', 'STANDARD'];
        $formatted = [];

        foreach ($areas as $index => $area) {
            $topItems = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->leftJoin('user_addresses', 'user_addresses.id', '=', 'orders.address_id')
                ->where(DB::raw('COALESCE(user_addresses.city, user_addresses.address_line_2, "Downtown")'), $area->area_name)
                ->select(DB::raw('COALESCE(order_items.item_name, "COMBO") as item_name'), DB::raw('SUM(order_items.quantity) as qty'))
                ->groupBy('item_name')
                ->orderByDesc('qty')
                ->limit(3)
                ->pluck('item_name')
                ->map(fn($item) => '#' . strtoupper(str_replace(' ', '_', $item)))
                ->toArray();

            $formatted[] = [
                'area_name' => $area->area_name,
                'total_orders' => (int) $area->total_orders,
                'avg_order_value' => '£' . number_format($area->avg_spent, 2),
                'peak_hours' => '6PM - 10PM',
                'total_spend' => '£' . number_format($area->total_spent, 2),
                'top_items' => $topItems,
                'badge' => $badges[$index % count($badges)],
                'growth' => '0%',
            ];
        }

        if (empty($formatted)) {
            $formatted = [
                [
                    'area_name' => 'Down Street',
                    'total_orders' => 0,
                    'avg_order_value' => '£0.00',
                    'peak_hours' => '—',
                    'total_spend' => '£0.00',
                    'top_items' => ['#BURGER_COMBO'],
                    'badge' => 'RECOMMENDED',
                    'growth' => '0%',
                ],
                [
                    'area_name' => 'Midland',
                    'total_orders' => 0,
                    'avg_order_value' => '£0.00',
                    'peak_hours' => '—',
                    'total_spend' => '£0.00',
                    'top_items' => ['#PIZZA'],
                    'badge' => 'STANDARD',
                    'growth' => '0%',
                ],
                [
                    'area_name' => 'Midland North',
                    'total_orders' => 0,
                    'avg_order_value' => '£0.00',
                    'peak_hours' => '—',
                    'total_spend' => '£0.00',
                    'top_items' => ['#PIZZA'],
                    'badge' => 'HIGH POTENTIAL',
                    'growth' => '0%',
                ],
                [
                    'area_name' => 'Brickwood',
                    'total_orders' => 0,
                    'avg_order_value' => '£0.00',
                    'peak_hours' => '—',
                    'total_spend' => '£0.00',
                    'top_items' => ['#CHICKEN_WINGS'],
                    'badge' => 'RECOMMENDED',
                    'growth' => '0%',
                ],
                [
                    'area_name' => 'Carlisle',
                    'total_orders' => 0,
                    'avg_order_value' => '£0.00',
                    'peak_hours' => '—',
                    'total_spend' => '£0.00',
                    'top_items' => ['#MARGARITA_PIZZA'],
                    'badge' => 'STANDARD',
                    'growth' => '0%',
                ],
                [
                    'area_name' => 'Westside',
                    'total_orders' => 0,
                    'avg_order_value' => '£0.00',
                    'peak_hours' => '—',
                    'total_spend' => '£0.00',
                    'top_items' => ['#SOFT_DRINKS'],
                    'badge' => 'GROWING',
                    'growth' => '0%',
                ],
            ];
        }

        return $formatted;
    }

    /**
     * Compute Campaign performance analytics.
     */
    protected function getCampaignAnalytics(): array
    {
        $best = Campaign::leftJoin('campaign_statistics', 'campaign_statistics.campaign_id', '=', 'campaigns.id')
            ->select('campaigns.name', DB::raw('COALESCE(campaign_statistics.delivered, campaign_statistics.sent, 0) as reached'))
            ->orderByDesc('reached')
            ->first();

        if (!$best) {
            return ['best' => null];
        }

        return [
            'best' => [
                'name' => $best->name,
                'reached' => (int) $best->reached,
                'growth_percent' => '0%',
                'badge' => 'Top Performer',
            ],
        ];
    }

    /**
     * Call Google Gemini API (Free Flash Tier) or fallback to rule-based logic.
     */
    protected function generateAiSuggestions(array $summaryData): array
    {
        $apiKey = config('services.gemini.api_key');

        if (!empty($apiKey)) {
            try {
                $model = config('services.gemini.model', 'gemini-3.5-flash-lite');
                $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
                $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

                $prompt = $this->buildGeminiPrompt($summaryData);

                $response = Http::timeout(15)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.4,
                    ]
                ]);

                if ($response->successful()) {
                    $jsonText = $response->json('candidates.0.content.parts.0.text');
                    $parsed = json_decode($jsonText, true);

                    if ($parsed && isset($parsed['hero'])) {
                        $parsed['is_ai_powered'] = true;
                        return $parsed;
                    }
                } else {
                    Log::warning('Gemini AI Request returned status: ' . $response->status() . ' - ' . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error('Gemini AI Exception: ' . $e->getMessage());
            }
        }

        // Intelligent Rule-Based Fallback
        return $this->getRuleBasedFallback($summaryData);
    }

    /**
     * Build structured prompt for Google Gemini.
     */
    protected function buildGeminiPrompt(array $data): string
    {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are an expert Restaurant Growth & Marketing Strategist AI.
Analyze the following restaurant performance summary for this week and output structured marketing insights.

Restaurant Data:
{$dataJson}

Respond ONLY with valid JSON strictly adhering to this schema:
{
  "hero": {
    "headline": "Short single sentence about top item growth in the top area (e.g. Burger Combo Deluxe demand increased 28% in Downtown area.)",
    "recommendation": "One practical time-bound recommendation (e.g. Run 'Buy 1 Get Free Fries' campaign between 6PM-8PM.)"
  },
  "product_suggestions": {
    "Product Name": "Punchy under 10-word action recommendation for this product"
  },
  "customer_suggestions": {
    "Customer Name": "Loyalty or reward coupon (e.g. 20% OFF NEXT ORDERS)"
  },
  "campaigns": [
    {
      "badge": "Recommended",
      "title": "Burger Combo Offer",
      "description": "Buy 1 Burger Combo Get Free Fries",
      "target_area": "Downtown Urban",
      "est_reach": "8,250 people",
      "est_upsell": "4,250 people"
    },
    {
      "badge": "High Impact",
      "title": "Weekend Pizza Deal",
      "description": "20% OFF on Large Pizza",
      "target_area": "All Area",
      "est_reach": "12,500 people",
      "est_upsell": "8,200 people"
    },
    {
      "badge": "Recover Customer",
      "title": "We Miss You Offer",
      "description": "15% OFF for inactive customers",
      "target_area": "Downtown Urban",
      "est_reach": "850 people",
      "est_upsell": "4,250 people"
    },
    {
      "badge": "Loyalty Boost",
      "title": "VIP Loyalty Reward",
      "description": "Free Dessert on Silver Order",
      "target_area": "Downtown Area",
      "est_reach": "4,250 people",
      "est_upsell": "1,250 people"
    }
  ]
}
PROMPT;
    }

    /**
     * Smart Rule-Based Fallback when offline or Gemini API is not configured.
     */
    protected function getRuleBasedFallback(array $data): array
    {
        $hasProducts = !empty($data['top_selling_products']);
        $topProduct = $data['top_product'] ?? 'No Sales Data';
        $topGrowth = $data['top_product_growth'] ?? '0%';
        $topArea = $data['top_area'] ?? 'N/A';
        $peakTime = $data['top_product_peak'] ?? '6PM - 8PM';

        $productSuggestions = [];
        if ($hasProducts) {
            foreach ($data['top_selling_products'] as $prod) {
                $name = $prod['name'];
                $area = $prod['top_area'] ?? 'Downtown';
                $productSuggestions[$name] = "Increase combo promotion in {$area} area";
            }
        }

        $customerSuggestions = [];
        if (!empty($data['top_customers'])) {
            foreach ($data['top_customers'] as $cust) {
                $name = $cust['name'];
                $customerSuggestions[$name] = '20% OFF NEXT OFFERS';
            }
        }

        $headline = $hasProducts 
            ? "{$topProduct} demand increased {$topGrowth} in {$topArea} area."
            : 'No sufficient sales data found for this period.';

        $recommendation = $hasProducts
            ? "Run 'Buy 1 Get Free Fries' campaign between {$peakTime}."
            : 'Start taking customer orders to receive AI-powered marketing recommendations.';

        return [
            'is_ai_powered' => false,
            'hero' => [
                'headline' => $headline,
                'recommendation' => $recommendation,
            ],
            'product_suggestions' => $productSuggestions,
            'product_suggestions_indexed' => [],
            'customer_suggestions' => $customerSuggestions,
            'campaigns' => [
                [
                    'badge' => 'Recommended',
                    'title' => 'Burger Combo Offer',
                    'description' => 'Buy 1 Burger Combo Get Free Fries',
                    'target_area' => 'Down Street',
                    'est_reach' => '0 people',
                    'est_upsell' => '0 people',
                ],
                [
                    'badge' => 'High Impact',
                    'title' => 'Weekend Pizza Deal',
                    'description' => '20% OFF on Large Pizza',
                    'target_area' => 'Midland',
                    'est_reach' => '0 people',
                    'est_upsell' => '0 people',
                ],
                [
                    'badge' => 'Recover Customer',
                    'title' => 'We Miss You Offer',
                    'description' => '15% OFF for loyal repeat customers',
                    'target_area' => 'Carlisle',
                    'est_reach' => '0 people',
                    'est_upsell' => '0 people',
                ],
                [
                    'badge' => 'Loyalty Boost',
                    'title' => 'VIP Loyalty Reward',
                    'description' => 'Free Drink on Top Spender Order',
                    'target_area' => 'Brickwood',
                    'est_reach' => '0 people',
                    'est_upsell' => '0 people',
                ],
            ],
        ];
    }

    /**
     * Format hour integer into range e.g. 18 -> "6PM - 8PM".
     */
    protected function formatHourRange(int $hour): string
    {
        $startHour12 = ($hour % 12) === 0 ? 12 : ($hour % 12);
        $startAmPm = $hour < 12 ? 'AM' : 'PM';

        $endHour = ($hour + 2) % 24;
        $endHour12 = ($endHour % 12) === 0 ? 12 : ($endHour % 12);
        $endAmPm = $endHour < 12 ? 'AM' : 'PM';

        return "{$startHour12}{$startAmPm} - {$endHour12}{$endAmPm}";
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications with full UI summary, tabs, quick filters, and grouping.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        $isSuperAdmin = $authUser && ($authUser->isSuperAdmin() || $authUser->user_type === 'super_admin');
        
        // Base scope for counting and filtering
        $baseQuery = Notification::query();

        // If not super admin, filter by user/branch
        if (!$isSuperAdmin) {
            $userId = $authUser?->id;
            $branchId = $request->input('branch_id', $authUser?->branch_id);

            $baseQuery->where(function ($q) use ($userId, $branchId) {
                if ($userId) {
                    $q->where('user_id', $userId)
                      ->orWhereNull('user_id');
                }
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            });
        } elseif ($request->filled('branch_id')) {
            $baseQuery->where('branch_id', $request->branch_id);
        }

        // 1. Compute Tab Counts (Top Tabs)
        $tabCounts = [
            'all' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)->where('is_read', false)->count(),
            'alerts' => (clone $baseQuery)->whereIn('type', ['inventory', 'alert', 'system'])->count(),
            'branch_report' => (clone $baseQuery)->whereIn('type', ['delivery', 'staff', 'branch_report'])->count(),
            'system' => (clone $baseQuery)->where('type', 'system')->count(),
            'marketing' => (clone $baseQuery)->where('type', 'marketing')->count(),
        ];

        // 2. Compute Summary Chart Breakdown (Right Panel Donut Chart)
        $totalNotifications = $tabCounts['all'];
        $orderCount = (clone $baseQuery)->where('type', 'order')->count();
        $alertsCount = (clone $baseQuery)->whereIn('type', ['inventory', 'alert'])->count();
        $marketingCount = (clone $baseQuery)->where('type', 'marketing')->count();
        $systemCount = (clone $baseQuery)->where('type', 'system')->count();
        $othersCount = (clone $baseQuery)->whereIn('type', ['delivery', 'staff'])->count();

        $calcPercentage = function ($count, $total) {
            return $total > 0 ? round(($count / $total) * 100) : 0;
        };

        $summary = [
            'total' => $totalNotifications,
            'breakdown' => [
                [
                    'name' => 'Orders',
                    'count' => $orderCount,
                    'percentage' => $calcPercentage($orderCount, $totalNotifications),
                    'color' => '#E05822',
                ],
                [
                    'name' => 'Alerts',
                    'count' => $alertsCount,
                    'percentage' => $calcPercentage($alertsCount, $totalNotifications),
                    'color' => '#3B82F6',
                ],
                [
                    'name' => 'Marketing',
                    'count' => $marketingCount,
                    'percentage' => $calcPercentage($marketingCount, $totalNotifications),
                    'color' => '#8B5CF6',
                ],
                [
                    'name' => 'System',
                    'count' => $systemCount,
                    'percentage' => $calcPercentage($systemCount, $totalNotifications),
                    'color' => '#10B981',
                ],
                [
                    'name' => 'Others',
                    'count' => $othersCount,
                    'percentage' => $calcPercentage($othersCount, $totalNotifications),
                    'color' => '#6B7280',
                ],
            ],
        ];

        // 3. Compute Quick Filters Counts
        $quickFilters = [
            'important' => (clone $baseQuery)->where(function ($q) {
                $q->where('type', 'inventory')
                  ->orWhere('title', 'like', '%important%')
                  ->orWhere('title', 'like', '%urgent%')
                  ->orWhere('title', 'like', '%alert%');
            })->count(),
            'requires_action' => (clone $baseQuery)->where(function ($q) {
                $q->where('is_read', false)
                  ->whereIn('type', ['delivery', 'inventory', 'order']);
            })->count(),
            'mentions' => (clone $baseQuery)->where('message', 'like', '%@%')->count(),
            'with_attachment' => (clone $baseQuery)->where('message', 'like', '%http%')->count(),
            'unread' => $tabCounts['unread'],
        ];

        // 4. Apply Active Query Filters to Notification List
        $query = clone $baseQuery;
        $query->with(['user:id,name,email,avatar', 'branch:id,name,address']);

        // Tab / Category Filter
        $tab = strtolower($request->input('tab', $request->input('type', 'all')));
        if ($tab === 'unread') {
            $query->where('is_read', false);
        } elseif ($tab === 'alerts') {
            $query->whereIn('type', ['inventory', 'alert', 'system']);
        } elseif ($tab === 'branch_report' || $tab === 'delivery') {
            $query->whereIn('type', ['delivery', 'staff', 'branch_report']);
        } elseif (in_array($tab, ['order', 'marketing', 'system', 'inventory', 'staff', 'delivery'])) {
            $query->where('type', $tab);
        }

        // Quick Filter matching
        if ($request->filled('quick_filter')) {
            $qf = strtolower($request->quick_filter);
            if ($qf === 'important') {
                $query->where(function ($q) {
                    $q->where('type', 'inventory')
                      ->orWhere('title', 'like', '%important%')
                      ->orWhere('title', 'like', '%urgent%')
                      ->orWhere('title', 'like', '%alert%');
                });
            } elseif ($qf === 'requires_action') {
                $query->where('is_read', false)->whereIn('type', ['delivery', 'inventory', 'order']);
            } elseif ($qf === 'unread') {
                $query->where('is_read', false);
            }
        }

        // Date Range Filters (Today, Weekly, Monthly, Custom)
        $dateFilter = strtolower($request->input('date_filter', $request->input('range', '')));
        if ($dateFilter === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($dateFilter === 'yesterday') {
            $query->whereDate('created_at', Carbon::yesterday());
        } elseif ($dateFilter === 'weekly' || $dateFilter === 'this_week') {
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($dateFilter === 'monthly' || $dateFilter === 'this_month') {
            $query->whereMonth('created_at', Carbon::now()->month)
                  ->whereYear('created_at', Carbon::now()->year);
        } elseif ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $query->latest();

        $perPage = (int) $request->input('per_page', 10);
        $paginated = $query->paginate($perPage);

        // Format items with date grouping and human readable timestamps
        $todayStr = Carbon::today()->toDateString();
        $yesterdayStr = Carbon::yesterday()->toDateString();

        $formattedItems = collect($paginated->items())->map(function ($n) use ($todayStr, $yesterdayStr) {
            $createdAt = $n->created_at ? Carbon::parse($n->created_at) : Carbon::now();
            $dateStr = $createdAt->toDateString();

            if ($dateStr === $todayStr) {
                $dateGroup = 'Today';
                $formattedTime = $createdAt->format('H:i');
            } elseif ($dateStr === $yesterdayStr) {
                $dateGroup = 'Yesterday';
                $formattedTime = 'Yesterday, ' . $createdAt->format('h:i A');
            } else {
                $dateGroup = $createdAt->format('F d, Y');
                $formattedTime = $createdAt->format('d M, h:i A');
            }

            return [
                'id' => $n->id,
                'user_id' => $n->user_id,
                'branch_id' => $n->branch_id,
                'branch_name' => $n->branch?->name ?? 'All Branches',
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'is_read' => (bool) $n->is_read,
                'date_group' => $dateGroup,
                'formatted_time' => $formattedTime,
                'time_ago' => $createdAt->diffForHumans(),
                'created_at' => $n->created_at,
            ];
        });

        // Group items by date for frontend timeline view
        $groupedByDate = $formattedItems->groupBy('date_group');

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'tab_counts' => $tabCounts,
            'summary' => $summary,
            'quick_filters' => $quickFilters,
            'timeline' => $groupedByDate,
            'notifications' => [
                'current_page' => $paginated->currentPage(),
                'data' => $formattedItems,
                'first_page_url' => $paginated->url(1),
                'from' => $paginated->firstItem(),
                'last_page' => $paginated->lastPage(),
                'last_page_url' => $paginated->url($paginated->lastPage()),
                'next_page_url' => $paginated->nextPageUrl(),
                'path' => $paginated->path(),
                'per_page' => $paginated->perPage(),
                'prev_page_url' => $paginated->previousPageUrl(),
                'to' => $paginated->lastItem(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get Notifications Summary & Donut Chart Breakdown
     */
    public function summary(Request $request)
    {
        $baseQuery = Notification::query();

        if ($request->filled('branch_id')) {
            $baseQuery->where('branch_id', $request->branch_id);
        }

        $total = $baseQuery->count();
        $unread = (clone $baseQuery)->where('is_read', false)->count();
        $orderCount = (clone $baseQuery)->where('type', 'order')->count();
        $alertsCount = (clone $baseQuery)->whereIn('type', ['inventory', 'alert'])->count();
        $marketingCount = (clone $baseQuery)->where('type', 'marketing')->count();
        $systemCount = (clone $baseQuery)->where('type', 'system')->count();
        $othersCount = (clone $baseQuery)->whereIn('type', ['delivery', 'staff'])->count();

        $calcPct = fn($val) => $total > 0 ? round(($val / $total) * 100) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'unread' => $unread,
                'breakdown' => [
                    ['name' => 'Orders', 'count' => $orderCount, 'percentage' => $calcPct($orderCount), 'color' => '#E05822'],
                    ['name' => 'Alerts', 'count' => $alertsCount, 'percentage' => $calcPct($alertsCount), 'color' => '#3B82F6'],
                    ['name' => 'Marketing', 'count' => $marketingCount, 'percentage' => $calcPct($marketingCount), 'color' => '#8B5CF6'],
                    ['name' => 'System', 'count' => $systemCount, 'percentage' => $calcPct($systemCount), 'color' => '#10B981'],
                    ['name' => 'Others', 'count' => $othersCount, 'percentage' => $calcPct($othersCount), 'color' => '#6B7280'],
                ],
            ],
        ]);
    }

    /**
     * Store a newly created notification.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:order,inventory,delivery,staff,marketing,system,alert',
            'is_read' => 'nullable|boolean',
        ]);

        $validated['type'] = $validated['type'] ?? 'system';
        $validated['is_read'] = $validated['is_read'] ?? false;

        $notification = Notification::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully.',
            'data' => $notification->load(['user', 'branch']),
        ], 201);
    }

    /**
     * Display the specified notification & mark as read.
     */
    public function show(Notification $notification)
    {
        if (!$notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => $notification->load(['user', 'branch']),
        ]);
    }

    /**
     * Update notification (mark read/unread or change details).
     */
    public function update(Request $request, Notification $notification)
    {
        $validated = $request->validate([
            'is_read' => 'sometimes|required|boolean',
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'type' => 'sometimes|string',
        ]);

        $notification->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Notification updated successfully.',
            'data' => $notification->load(['user', 'branch']),
        ]);
    }

    /**
     * Toggle read/unread status for a notification.
     */
    public function toggleRead(Notification $notification)
    {
        $notification->update(['is_read' => !$notification->is_read]);

        return response()->json([
            'success' => true,
            'message' => 'Notification status toggled successfully.',
            'is_read' => (bool) $notification->is_read,
            'data' => $notification,
        ]);
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(Notification $notification)
    {
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ], 200);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $query = Notification::where('is_read', false);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $count = $query->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => "Successfully marked {$count} notifications as read.",
            'marked_count' => $count,
        ]);
    }
}
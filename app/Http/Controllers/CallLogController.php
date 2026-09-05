<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CallLog;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    /**
     * Unified Call Logs Overview & Page Bundle.
     * Returns Top 5 Metrics Cards, Call Logs Panel, Converted Call Orders, History Cards, and Stats in ONE single call.
     * Completely null-safe for empty databases and nullable attributes.
     */
    public function overview(Request $request)
    {
        $branchId = $request->input('branch_id');
        $period = $request->input('period', 'this_week');

        [$currentStart, $currentEnd, $prevStart, $prevEnd] = $this->resolvePeriodDates($period, $request);

        $baseQuery = CallLog::query();
        if (!empty($branchId)) {
            $baseQuery->forBranch($branchId);
        }

        // -------------------------------------------------------------
        // 1. TOP 5 METRICS CARDS (Current vs Previous Period)
        // -------------------------------------------------------------
        $currentQuery = (clone $baseQuery)->whereBetween('started_at', [$currentStart, $currentEnd]);
        $prevQuery = (clone $baseQuery)->whereBetween('started_at', [$prevStart, $prevEnd]);

        $currentTotal = (clone $currentQuery)->count();
        $prevTotal = (clone $prevQuery)->count();

        $currentConverted = (clone $currentQuery)->converted()->count();
        $prevConverted = (clone $prevQuery)->converted()->count();

        $currentMissed = (clone $currentQuery)->missed()->count();
        $prevMissed = (clone $prevQuery)->missed()->count();

        $currentConvRate = $currentTotal > 0 ? round(($currentConverted / $currentTotal) * 100, 1) : 0.0;
        $prevConvRate = $prevTotal > 0 ? round(($prevConverted / $prevTotal) * 100, 1) : 0.0;

        $currentTotalDuration = (clone $currentQuery)->sum('call_duration');
        $currentAvgDuration = $currentTotal > 0 ? (int) round($currentTotalDuration / $currentTotal) : 0;

        $prevTotalDuration = (clone $prevQuery)->sum('call_duration');
        $prevAvgDuration = $prevTotal > 0 ? (int) round($prevTotalDuration / $prevTotal) : 0;

        $percentageChange = function ($current, $previous) {
            if ((float) $previous === 0.0) {
                return $current > 0 ? 100.0 : 0.0;
            }
            return round((($current - $previous) / $previous) * 100, 1);
        };

        $formatDuration = function ($seconds) {
            $seconds = max(0, (int) $seconds);
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return sprintf('%02d:%02d', $minutes, $secs);
        };

        $summaryCards = [
            'total_calls' => [
                'label' => 'TOTAL CALLS',
                'value' => $currentTotal,
                'previous_value' => $prevTotal,
                'change_percentage' => $percentageChange($currentTotal, $prevTotal),
                'is_positive' => $currentTotal >= $prevTotal,
            ],
            'call_converted' => [
                'label' => 'CALL CONVERTED',
                'value' => $currentConverted,
                'previous_value' => $prevConverted,
                'change_percentage' => $percentageChange($currentConverted, $prevConverted),
                'is_positive' => $currentConverted >= $prevConverted,
            ],
            'missed_calls' => [
                'label' => 'MISSED CALLS',
                'value' => $currentMissed,
                'previous_value' => $prevMissed,
                'change_percentage' => $percentageChange($currentMissed, $prevMissed),
                'is_positive' => $currentMissed <= $prevMissed,
            ],
            'conversion_rate' => [
                'label' => 'CONVERSION RATE',
                'value' => $currentConvRate . '%',
                'numeric_value' => $currentConvRate,
                'previous_value' => $prevConvRate . '%',
                'change_percentage' => $percentageChange($currentConvRate, $prevConvRate),
                'is_positive' => $currentConvRate >= $prevConvRate,
            ],
            'avg_call_duration' => [
                'label' => 'AVG. CALL DURATION',
                'value' => $formatDuration($currentAvgDuration),
                'seconds' => $currentAvgDuration,
                'previous_value' => $formatDuration($prevAvgDuration),
                'previous_seconds' => $prevAvgDuration,
                'change_percentage' => $percentageChange($currentAvgDuration, $prevAvgDuration),
                'is_positive' => true,
            ],
        ];

        // -------------------------------------------------------------
        // 2. CALL LOGS PANEL (Main Table with filters & search)
        // -------------------------------------------------------------
        $panelQuery = CallLog::with([
            'branch',
            'user',
            'staff.role',
            'order.items.menuItem',
            'order.address',
            'order.user',
            'order.assignedDriver.user',
        ]);

        if (!empty($branchId)) {
            $panelQuery->forBranch($branchId);
        }

        if ($request->filled('tab')) {
            $tab = strtolower(trim($request->tab));
            if ($tab === 'converted') {
                $panelQuery->converted();
            } elseif ($tab === 'missed') {
                $panelQuery->missed();
            } elseif ($tab === 'answered') {
                $panelQuery->answered();
            } elseif ($tab === 'on_delivery') {
                $panelQuery->whereHas('order', function ($oq) {
                    $oq->whereIn('order_status', ['out_for_delivery', 'picked_up', 'in_transit']);
                });
            } elseif ($tab === 'available') {
                $panelQuery->whereHas('staff', function ($sq) {
                    $sq->where('status', 'active');
                });
            } elseif ($tab === 'break') {
                $panelQuery->whereHas('staff', function ($sq) {
                    $sq->where('status', 'on_break');
                });
            } elseif ($tab === 'offline') {
                $panelQuery->whereHas('staff', function ($sq) {
                    $sq->where('status', 'inactive');
                });
            }
        }

        if ($request->filled('call_status')) {
            $panelQuery->byStatus($request->call_status);
        }

        if ($request->filled('call_outcome')) {
            $panelQuery->byOutcome($request->call_outcome);
        }

        if ($request->filled('driver_status')) {
            $panelQuery->whereHas('order.assignedDriver', function ($dq) use ($request) {
                $dq->where('status', $request->driver_status);
            });
        }

        if ($request->filled('vehicle_type')) {
            $panelQuery->whereHas('order.assignedDriver', function ($dq) use ($request) {
                $dq->where('vehicle_type', $request->vehicle_type);
            });
        }

        if ($request->filled('shift')) {
            $panelQuery->whereHas('staff', function ($sq) use ($request) {
                $sq->where('shift', $request->shift);
            });
        }

        if ($request->filled('search')) {
            $panelQuery->search($request->search);
        }

        $this->applyDateFilter($panelQuery, $request);

        $sortBy = $request->input('sort_by', 'started_at');
        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $panelQuery->orderBy($sortBy, $sortDirection)->orderBy('id', 'desc');

        $callLogsPanel = $panelQuery->paginate($request->input('panel_per_page', 15), ['*'], 'panel_page');

        // Transform panel items with null safety
        $callLogsPanel->getCollection()->transform(function ($log) {
            $order = $log->order;
            $customerName = $log->customer_name ?: ($order?->customer_name ?: ($log->user?->name ?: null));
            $postcode = $log->postcode ?: ($order?->address?->postcode ?: ($log->user?->defaultAddress?->postcode ?: null));

            if ($order && !empty($order->order_number)) {
                $outcomeDisplay = '#' . ltrim($order->order_number, '#');
            } elseif ($log->call_status === 'missed') {
                $outcomeDisplay = 'Missed Call';
            } elseif (!empty($log->call_outcome)) {
                $outcomeDisplay = ucfirst(str_replace('_', ' ', $log->call_outcome));
            } else {
                $outcomeDisplay = null;
            }

            $linkedOrder = null;
            if ($order && !empty($order->order_number)) {
                $totalText = $order->total !== null ? ' (£' . number_format($order->total, 0) . ')' : '';
                $linkedOrder = '#' . ltrim($order->order_number, '#') . $totalText;
            }

            return [
                'id' => $log->id,
                'time' => $log->formatted_time,
                'date' => $log->formatted_date,
                'call_number' => $log->phone,
                'customer' => $customerName,
                'duration' => $log->duration_formatted,
                'duration_seconds' => $log->call_duration,
                'call_status' => $log->call_status,
                'call_status_label' => $log->call_status ? ucfirst($log->call_status) : null,
                'outcome' => $outcomeDisplay,
                'outcome_raw' => $log->call_outcome,
                'linked_order' => $linkedOrder,
                'order_id' => $log->order_id,
                'order_number' => $order?->order_number,
                'postcode' => $postcode,
                'action' => $order ? 'View Order' : 'Call Back',
                'order' => $order,
                'staff' => $log->staff,
                'branch' => $log->branch,
                'user' => $log->user,
                'notes' => $log->notes,
                'recording_url' => $log->recording_url,
            ];
        });

        // -------------------------------------------------------------
        // 3. CONVERTED CALL ORDERS (Section 2 Table)
        // -------------------------------------------------------------
        $convertedQuery = CallLog::with([
            'order.items.menuItem',
            'order.address',
            'order.user',
            'order.branch',
            'order.assignedDriver.user',
            'branch',
            'user',
        ])
        ->converted();

        if (!empty($branchId)) {
            $convertedQuery->forBranch($branchId);
        }

        if ($request->filled('search')) {
            $convertedQuery->search($request->search);
        }

        $this->applyDateFilter($convertedQuery, $request);
        $convertedQuery->orderBy('started_at', 'desc')->orderBy('id', 'desc');

        $convertedOrders = $convertedQuery->paginate($request->input('converted_per_page', 10), ['*'], 'converted_page');

        $convertedOrders->getCollection()->transform(function ($callLog) {
            $order = $callLog->order;
            $customerName = $callLog->customer_name ?: ($order?->customer_name ?: ($callLog->user?->name ?: null));
            $postcode = $callLog->postcode ?: ($order?->address?->postcode ?: null);
            $orderType = $order?->order_type ?: null;
            $orderStatus = $order?->order_status ?: null;
            $orderTotal = $order?->total !== null ? (float) $order->total : null;
            $orderNumber = $order?->order_number ?: null;

            $orderFormatted = null;
            if ($orderNumber) {
                $totalText = $orderTotal !== null ? ' (£' . number_format($orderTotal, 0) . ')' : '';
                $orderFormatted = '#' . ltrim($orderNumber, '#') . $totalText;
            }

            return [
                'id' => $callLog->id,
                'time' => $callLog->formatted_time,
                'date' => $callLog->formatted_date,
                'call_number' => $callLog->phone,
                'customer' => $customerName,
                'duration' => $callLog->duration_formatted,
                'duration_seconds' => $callLog->call_duration,
                'order_id' => $callLog->order_id,
                'order_number' => $orderNumber ? '#' . ltrim($orderNumber, '#') : null,
                'order_formatted' => $orderFormatted,
                'order_type' => $orderType,
                'order_type_label' => $orderType ? ucfirst(str_replace('_', ' ', $orderType)) : null,
                'status' => $orderStatus,
                'status_label' => $orderStatus ? ucfirst(str_replace('_', ' ', $orderStatus)) : null,
                'postcode' => $postcode,
                'total_amount' => $orderTotal,
                'order' => $order,
                'action' => $order ? 'View Order' : null,
            ];
        });

        // -------------------------------------------------------------
        // 4. ORDER HISTORY & CALL LOGS (Section 3 Cards Feed)
        // -------------------------------------------------------------
        $historyQuery = CallLog::with([
            'order.address',
            'order.branch',
            'branch',
            'user',
        ]);

        if (!empty($branchId)) {
            $historyQuery->forBranch($branchId);
        }

        if ($request->filled('search')) {
            $historyQuery->search($request->search);
        }

        $this->applyDateFilter($historyQuery, $request);
        $historyQuery->orderBy('started_at', 'desc')->orderBy('id', 'desc');

        $historyLogs = $historyQuery->paginate($request->input('history_per_page', 12), ['*'], 'history_page');

        $historyLogs->getCollection()->transform(function ($callLog) {
            $order = $callLog->order;
            $customerName = $callLog->customer_name ?: ($order?->customer_name ?: ($callLog->user?->name ?: null));
            $postcode = $callLog->postcode ?: ($order?->address?->postcode ?: null);
            $locationName = $callLog->branch?->name ?: null;

            if ($locationName && $postcode) {
                $locationDisplay = "{$locationName} ({$postcode})";
            } else {
                $locationDisplay = $locationName ?: ($postcode ?: null);
            }

            if ($callLog->call_outcome === 'converted' || !empty($callLog->order_id)) {
                $badgeText = 'Order Converted';
                $badgeVariant = 'purple';
            } elseif ($callLog->call_status === 'missed') {
                $badgeText = 'Missed Call';
                $badgeVariant = 'danger';
            } else {
                $badgeText = 'Order Placed';
                $badgeVariant = 'success';
            }

            return [
                'id' => $callLog->id,
                'time' => $callLog->formatted_time,
                'date' => $callLog->formatted_date,
                'call_duration' => $callLog->duration_formatted,
                'duration_seconds' => $callLog->call_duration,
                'customer_name' => $customerName,
                'phone' => $callLog->phone,
                'location' => $locationDisplay,
                'postcode' => $postcode,
                'badge_status' => $badgeText,
                'badge_variant' => $badgeVariant,
                'call_status' => $callLog->call_status,
                'call_outcome' => $callLog->call_outcome,
                'order_id' => $callLog->order_id,
                'order_number' => $order?->order_number,
                'order_total' => $order?->total !== null ? (float) $order->total : null,
            ];
        });

        // -------------------------------------------------------------
        // 5. SIMPLE STATS & DAILY TREND CHARTS
        // -------------------------------------------------------------
        $statusBreakdown = (clone $currentQuery)
            ->selectRaw('call_status, count(*) as count')
            ->groupBy('call_status')
            ->pluck('count', 'call_status')
            ->all();

        $outcomeBreakdown = (clone $currentQuery)
            ->whereNotNull('call_outcome')
            ->selectRaw('call_outcome, count(*) as count')
            ->groupBy('call_outcome')
            ->pluck('count', 'call_outcome')
            ->all();

        $dailyTrend = collect();
        $datePointer = $currentStart->copy();
        while ($datePointer->lte($currentEnd)) {
            $dayStart = $datePointer->copy()->startOfDay();
            $dayEnd = $datePointer->copy()->endOfDay();

            $dayCalls = (clone $baseQuery)->whereBetween('started_at', [$dayStart, $dayEnd])->count();
            $dayConverted = (clone $baseQuery)->whereBetween('started_at', [$dayStart, $dayEnd])->converted()->count();
            $dayMissed = (clone $baseQuery)->whereBetween('started_at', [$dayStart, $dayEnd])->missed()->count();

            $dailyTrend->push([
                'date' => $datePointer->toDateString(),
                'day' => $datePointer->format('D'),
                'total_calls' => $dayCalls,
                'converted' => $dayConverted,
                'missed' => $dayMissed,
            ]);

            $datePointer->addDay();
        }

        $stats = [
            'total_calls' => $currentTotal,
            'answered_calls' => $statusBreakdown['answered'] ?? 0,
            'missed_calls' => $statusBreakdown['missed'] ?? 0,
            'busy_calls' => $statusBreakdown['busy'] ?? 0,
            'cancelled_calls' => $statusBreakdown['cancelled'] ?? 0,
            'converted_calls' => $currentConverted,
            'conversion_rate' => $currentConvRate,
            'total_duration_seconds' => (int) $currentTotalDuration,
            'average_duration_seconds' => $currentAvgDuration,
            'average_duration_formatted' => $formatDuration($currentAvgDuration),
            'outcomes' => $outcomeBreakdown,
        ];

        // -------------------------------------------------------------
        // FINAL UNIFIED JSON RESPONSE
        // -------------------------------------------------------------
        return response()->json([
            'summary' => $summaryCards,
            'call_logs_panel' => $callLogsPanel,
            'converted_orders' => $convertedOrders,
            'history_logs' => $historyLogs,
            'stats' => $stats,
            'daily_trend' => $dailyTrend,
            'period' => [
                'current_start' => $currentStart->toDateTimeString(),
                'current_end' => $currentEnd->toDateTimeString(),
                'previous_start' => $prevStart->toDateTimeString(),
                'previous_end' => $prevEnd->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Display a listing of call logs.
     */
    public function index(Request $request)
    {
        $query = CallLog::with([
            'branch',
            'user',
            'staff.role',
            'order.items.menuItem',
            'order.address',
            'order.user',
            'order.assignedDriver.user',
        ]);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('staff_id')) {
            $query->byStaff($request->staff_id);
        }

        if ($request->filled('call_type')) {
            $query->byType($request->call_type);
        }

        if ($request->filled('call_status')) {
            $query->byStatus($request->call_status);
        }

        if ($request->filled('call_outcome')) {
            $query->byOutcome($request->call_outcome);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $this->applyDateFilter($query, $request);

        $sortBy = $request->input('sort_by', 'started_at');
        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'branch_id', 'call_type', 'call_status', 'call_duration', 'started_at', 'created_at', 'customer_name', 'phone'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('started_at', 'desc')->orderBy('id', 'desc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created call log in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'staff_id' => 'nullable|exists:staff,id',
            'order_id' => 'nullable|exists:orders,id',
            'customer_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
            'postcode' => 'nullable|string|max:20',
            'call_sid' => 'nullable|string|max:255',
            'call_type' => 'nullable|string|in:incoming,outgoing',
            'call_status' => 'nullable|string|in:answered,missed,busy,cancelled',
            'call_duration' => 'nullable|integer|min:0',
            'call_outcome' => 'nullable|string|in:converted,no_order,callback,complaint,inquiry,reservation,missed_call',
            'notes' => 'nullable|string',
            'recording_url' => 'nullable|string|max:500',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date',
        ]);

        if (empty($validated['started_at'])) {
            $validated['started_at'] = now();
        }

        if ((!isset($validated['call_duration']) || (int)$validated['call_duration'] === 0) && !empty($validated['ended_at'])) {
            $start = Carbon::parse($validated['started_at']);
            $end = Carbon::parse($validated['ended_at']);
            $validated['call_duration'] = (int) $start->diffInSeconds($end);
        }

        if (!empty($validated['order_id']) && empty($validated['call_outcome'])) {
            $validated['call_outcome'] = 'converted';
        }

        $callLog = CallLog::create($validated);

        return response()->json($callLog->load([
            'branch',
            'user',
            'staff',
            'order.items.menuItem',
            'order.address',
        ]), 201);
    }

    /**
     * Display the specified call log.
     */
    public function show(CallLog $callLog)
    {
        return response()->json($callLog->load([
            'branch',
            'user',
            'staff',
            'order.items.menuItem',
            'order.address',
            'order.assignedDriver.user',
        ]));
    }

    /**
     * Update the specified call log in storage.
     */
    public function update(Request $request, CallLog $callLog)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'staff_id' => 'nullable|exists:staff,id',
            'order_id' => 'nullable|exists:orders,id',
            'customer_name' => 'nullable|string|max:255',
            'phone' => 'sometimes|required|string|max:50',
            'postcode' => 'nullable|string|max:20',
            'call_sid' => 'nullable|string|max:255',
            'call_type' => 'sometimes|required|string|in:incoming,outgoing',
            'call_status' => 'sometimes|required|string|in:answered,missed,busy,cancelled',
            'call_duration' => 'nullable|integer|min:0',
            'call_outcome' => 'nullable|string|in:converted,no_order,callback,complaint,inquiry,reservation,missed_call',
            'notes' => 'nullable|string',
            'recording_url' => 'nullable|string|max:500',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date',
        ]);

        $startedAt = $validated['started_at'] ?? $callLog->started_at;
        $endedAt = $validated['ended_at'] ?? $callLog->ended_at;

        if (!isset($validated['call_duration']) && $startedAt && $endedAt) {
            $start = Carbon::parse($startedAt);
            $end = Carbon::parse($endedAt);
            $validated['call_duration'] = (int) $start->diffInSeconds($end);
        }

        if (!empty($validated['order_id']) && empty($validated['call_outcome'])) {
            $validated['call_outcome'] = 'converted';
        }

        $callLog->update($validated);

        return response()->json($callLog->load([
            'branch',
            'user',
            'staff',
            'order.items.menuItem',
            'order.address',
        ]));
    }

    /**
     * Remove the specified call log from storage.
     */
    public function destroy(CallLog $callLog)
    {
        $callLog->delete();

        return response()->json(null, 204);
    }

    /**
     * Quick action: Convert Call Log to an Order or link an existing order.
     */
    public function convertOrder(Request $request, CallLog $callLog)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'notes' => 'nullable|string',
        ]);

        $callLog->update([
            'order_id' => $validated['order_id'],
            'call_outcome' => 'converted',
            'notes' => !empty($validated['notes']) ? ($callLog->notes ? $callLog->notes . "\n" . $validated['notes'] : $validated['notes']) : $callLog->notes,
        ]);

        return response()->json([
            'message' => 'Call log successfully linked and marked as converted.',
            'call_log' => $callLog->load(['branch', 'user', 'staff', 'order.items.menuItem', 'order.address']),
        ]);
    }

    /**
     * Quick action: Log a callback attempt or mark callback status.
     */
    public function logCallback(Request $request, CallLog $callLog)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'call_outcome' => 'nullable|string|in:converted,callback,no_order,complaint,inquiry',
        ]);

        $callLog->update([
            'call_outcome' => $validated['call_outcome'] ?? 'callback',
            'notes' => !empty($validated['notes']) ? ($callLog->notes ? $callLog->notes . "\n[Callback]: " . $validated['notes'] : "[Callback]: " . $validated['notes']) : $callLog->notes,
        ]);

        return response()->json([
            'message' => 'Callback recorded successfully.',
            'call_log' => $callLog->load(['branch', 'user', 'staff', 'order']),
        ]);
    }

    /**
     * Dedicated Converted Orders endpoint.
     */
    public function convertedOrders(Request $request)
    {
        $query = CallLog::with([
            'order.items.menuItem',
            'order.address',
            'order.user',
            'order.branch',
            'order.assignedDriver.user',
            'branch',
            'user',
        ])
        ->converted();

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $this->applyDateFilter($query, $request);

        $query->orderBy('started_at', 'desc')->orderBy('id', 'desc');

        $paginated = $query->paginate($request->input('per_page', 15));

        $paginated->getCollection()->transform(function ($callLog) {
            $order = $callLog->order;
            $customerName = $callLog->customer_name ?: ($order?->customer_name ?: ($callLog->user?->name ?: null));
            $postcode = $callLog->postcode ?: ($order?->address?->postcode ?: null);
            $orderType = $order?->order_type ?: null;
            $orderStatus = $order?->order_status ?: null;
            $orderTotal = $order?->total !== null ? (float) $order->total : null;
            $orderNumber = $order?->order_number ?: null;

            $orderFormatted = null;
            if ($orderNumber) {
                $totalText = $orderTotal !== null ? ' (£' . number_format($orderTotal, 0) . ')' : '';
                $orderFormatted = '#' . ltrim($orderNumber, '#') . $totalText;
            }

            return [
                'id' => $callLog->id,
                'time' => $callLog->formatted_time,
                'date' => $callLog->formatted_date,
                'call_number' => $callLog->phone,
                'customer' => $customerName,
                'duration' => $callLog->duration_formatted,
                'duration_seconds' => $callLog->call_duration,
                'order_id' => $callLog->order_id,
                'order_number' => $orderNumber ? '#' . ltrim($orderNumber, '#') : null,
                'order_formatted' => $orderFormatted,
                'order_type' => $orderType,
                'order_type_label' => $orderType ? ucfirst(str_replace('_', ' ', $orderType)) : null,
                'status' => $orderStatus,
                'status_label' => $orderStatus ? ucfirst(str_replace('_', ' ', $orderStatus)) : null,
                'postcode' => $postcode,
                'total_amount' => $orderTotal,
                'order' => $order,
                'action' => $order ? 'View Order' : null,
            ];
        });

        return response()->json($paginated);
    }

    /**
     * Dedicated History endpoint.
     */
    public function history(Request $request)
    {
        $query = CallLog::with([
            'order.address',
            'order.branch',
            'branch',
            'user',
        ]);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $this->applyDateFilter($query, $request);

        $query->orderBy('started_at', 'desc')->orderBy('id', 'desc');

        $paginated = $query->paginate($request->input('per_page', 12));

        $paginated->getCollection()->transform(function ($callLog) {
            $order = $callLog->order;
            $customerName = $callLog->customer_name ?: ($order?->customer_name ?: ($callLog->user?->name ?: null));
            $postcode = $callLog->postcode ?: ($order?->address?->postcode ?: null);
            $locationName = $callLog->branch?->name ?: null;

            if ($locationName && $postcode) {
                $locationDisplay = "{$locationName} ({$postcode})";
            } else {
                $locationDisplay = $locationName ?: ($postcode ?: null);
            }

            if ($callLog->call_outcome === 'converted' || !empty($callLog->order_id)) {
                $badgeText = 'Order Converted';
                $badgeVariant = 'purple';
            } elseif ($callLog->call_status === 'missed') {
                $badgeText = 'Missed Call';
                $badgeVariant = 'danger';
            } else {
                $badgeText = 'Order Placed';
                $badgeVariant = 'success';
            }

            return [
                'id' => $callLog->id,
                'time' => $callLog->formatted_time,
                'date' => $callLog->formatted_date,
                'call_duration' => $callLog->duration_formatted,
                'duration_seconds' => $callLog->call_duration,
                'customer_name' => $customerName,
                'phone' => $callLog->phone,
                'location' => $locationDisplay,
                'postcode' => $postcode,
                'badge_status' => $badgeText,
                'badge_variant' => $badgeVariant,
                'call_status' => $callLog->call_status,
                'call_outcome' => $callLog->call_outcome,
                'order_id' => $callLog->order_id,
                'order_number' => $order?->order_number,
                'order_total' => $order?->total !== null ? (float) $order->total : null,
            ];
        });

        return response()->json($paginated);
    }

    /**
     * Dedicated stats endpoint.
     */
    public function stats(Request $request)
    {
        $query = CallLog::query();

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        $this->applyDateFilter($query, $request);

        $totalCalls = (clone $query)->count();
        $answeredCalls = (clone $query)->where('call_status', 'answered')->count();
        $missedCalls = (clone $query)->where('call_status', 'missed')->count();
        $busyCalls = (clone $query)->where('call_status', 'busy')->count();
        $cancelledCalls = (clone $query)->where('call_status', 'cancelled')->count();
        $convertedCalls = (clone $query)->converted()->count();

        $totalDuration = (clone $query)->sum('call_duration');
        $avgDuration = $totalCalls > 0 ? (int) round($totalDuration / $totalCalls) : 0;
        $conversionRate = $totalCalls > 0 ? round(($convertedCalls / $totalCalls) * 100, 1) : 0.0;

        $minutes = floor($avgDuration / 60);
        $seconds = $avgDuration % 60;
        $avgDurationFormatted = sprintf('%02d:%02d', $minutes, $seconds);

        $outcomes = (clone $query)
            ->whereNotNull('call_outcome')
            ->selectRaw('call_outcome, count(*) as count')
            ->groupBy('call_outcome')
            ->pluck('count', 'call_outcome');

        return response()->json([
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'missed_calls' => $missedCalls,
            'busy_calls' => $busyCalls,
            'cancelled_calls' => $cancelledCalls,
            'converted_calls' => $convertedCalls,
            'conversion_rate' => $conversionRate,
            'total_duration_seconds' => (int) $totalDuration,
            'average_duration_seconds' => $avgDuration,
            'average_duration_formatted' => $avgDurationFormatted,
            'outcomes' => $outcomes,
        ]);
    }

    /**
     * Apply date range or period filtering to query.
     */
    protected function applyDateFilter($query, Request $request): void
    {
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('started_at', [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay(),
            ]);
        } elseif ($request->filled('date_from')) {
            $query->where('started_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        } elseif ($request->filled('date_to')) {
            $query->where('started_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        } elseif ($request->filled('period')) {
            $today = Carbon::today();
            switch ($request->period) {
                case 'today':
                    $query->whereDate('started_at', $today);
                    break;
                case 'yesterday':
                    $query->whereDate('started_at', $today->copy()->subDay());
                    break;
                case 'this_week':
                    $query->whereBetween('started_at', [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()]);
                    break;
                case 'last_week':
                    $lastWeek = $today->copy()->subWeek();
                    $query->whereBetween('started_at', [$lastWeek->copy()->startOfWeek(), $lastWeek->copy()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereBetween('started_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()]);
                    break;
                case 'last_month':
                    $lastMonth = $today->copy()->subMonth();
                    $query->whereBetween('started_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()]);
                    break;
            }
        }
    }

    /**
     * Resolve date ranges for period comparison.
     */
    protected function resolvePeriodDates(string $period, Request $request): array
    {
        $today = Carbon::today();

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $currentStart = Carbon::parse($request->date_from)->startOfDay();
            $currentEnd = Carbon::parse($request->date_to)->endOfDay();
            $diffInDays = $currentStart->diffInDays($currentEnd) ?: 1;

            $prevStart = $currentStart->copy()->subDays($diffInDays + 1)->startOfDay();
            $prevEnd = $currentStart->copy()->subSecond();

            return [$currentStart, $currentEnd, $prevStart, $prevEnd];
        }

        switch ($period) {
            case 'today':
                $currentStart = $today->copy()->startOfDay();
                $currentEnd = $today->copy()->endOfDay();
                $prevStart = $today->copy()->subDay()->startOfDay();
                $prevEnd = $today->copy()->subDay()->endOfDay();
                break;

            case 'this_month':
                $currentStart = $today->copy()->startOfMonth();
                $currentEnd = $today->copy()->endOfMonth();
                $prevStart = $today->copy()->subMonth()->startOfMonth();
                $prevEnd = $today->copy()->subMonth()->endOfMonth();
                break;

            case 'this_week':
            default:
                $currentStart = $today->copy()->startOfWeek();
                $currentEnd = $today->copy()->endOfWeek();
                $prevStart = $currentStart->copy()->subWeek();
                $prevEnd = $currentEnd->copy()->subWeek();
                break;
        }

        return [$currentStart, $currentEnd, $prevStart, $prevEnd];
    }
}
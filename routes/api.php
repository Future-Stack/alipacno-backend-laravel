<?php

use App\Http\Controllers\POS\POSController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Stripe\StripeController;
use App\Http\Controllers\Subcategory\SubcategoryController;
use App\Http\Controllers\Wishlish\WishlistController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\HqAdminController;
use App\Http\Controllers\BranchAdminController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\UserAddressController;

use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSettingController;
use App\Http\Controllers\DeliveryAreaController;
use App\Http\Controllers\RestaurantTableController;
use App\Http\Controllers\TableReservationController;

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\ItemSizeController;
use App\Http\Controllers\CookingPreferenceController;
use App\Http\Controllers\SpiceLevelController;
use App\Http\Controllers\ToppingController;
use App\Http\Controllers\RecipeController;

use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\LoyaltyPointController;
use App\Http\Controllers\ReviewController;

use App\Http\Controllers\PosSessionController;
use App\Http\Controllers\KitchenStationController;
use App\Http\Controllers\KitchenOrderController;

use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\StockConversionController;

use App\Http\Controllers\DriverController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryFeeTierController;
use App\Http\Controllers\DriverShiftController;
use App\Http\Controllers\DriverPayoutController;
use App\Http\Controllers\StaffPayoutController;

use App\Http\Controllers\CallLogController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\CustomerTagController;
use App\Http\Controllers\CustomerNoteController;
use App\Http\Controllers\CustomerSegmentController;

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignRecipientController;
use App\Http\Controllers\CampaignMessageController;
use App\Http\Controllers\CampaignAutomationFlowController;
use App\Http\Controllers\CampaignStatisticController;

use App\Http\Controllers\DigitalScreenController;
use App\Http\Controllers\ScreenGroupController;
use App\Http\Controllers\SignageContentController;
use App\Http\Controllers\ScreenPlaylistController;
use App\Http\Controllers\ScreenScheduleController;

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationSettingController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SavedReportController;
use App\Http\Controllers\ScheduledReportController;
use App\Http\Controllers\AiInsightController;
use App\Http\Controllers\AiRecommendationController;
use App\Http\Controllers\ChatController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Page\PageController;

use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\User\DeleteUsersController;
use App\Http\Controllers\DriverLocationController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Http\Request;


/*
|--------------------------------------------------------------------------
| API Routes - Enterprise Multi-Branch Restaurant Platform
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::get('/',function ()
    {
        return response()->json([
            'status' => 200,
            'message' => 'OK'
        ]);
    });

    // Broadcasting Auth (for WebSocket private channels)
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    })->middleware('auth:sanctum');

      // Public Pages
        Route::get('pages', [PageController::class, 'index']);
        Route::get('pages/{page_id}', [PageController::class, 'show']);

    // Executive & Operational Dashboard Endpoints
    Route::get('dashboard/hq-overview', [DashboardController::class, 'hqOverview']);
    Route::get('dashboard/branch-overview', [DashboardController::class, 'branchOverview']);
    Route::get('dashboard/branch-dashboard', [DashboardController::class, 'branchOverview']);
    Route::get('dashboard/branch', [DashboardController::class, 'branchOverview']);
    Route::get('dashboard/earnings-analytics', [DashboardController::class, 'earningsAnalytics']);
    Route::get('dashboard/earnings', [DashboardController::class, 'earningsAnalytics']);
    Route::get('dashboard/order-management', [DashboardController::class, 'orderManagement']);
    Route::get('dashboard/order-report', [DashboardController::class, 'orderManagement']);
    Route::get('dashboard/orders-report', [DashboardController::class, 'orderManagement']);
    Route::get('dashboard/ai-insights', [AiInsightController::class, 'dashboard']);
    Route::get('dashboard/ai-insights/refresh', [AiInsightController::class, 'refresh']);

    // Super Admin / HQ Deliveries Management (Global across all branches)
    Route::get('dashboard/hq/deliveries', [DashboardController::class, 'hqDeliveries']);
    Route::get('dashboard/hq/deliveries-management', [DashboardController::class, 'hqDeliveries']);
    Route::get('dashboard/hq-deliveries', [DashboardController::class, 'hqDeliveries']);

    // Branch Admin / Manager Deliveries Management (Scoped strictly to specific Branch)
    Route::get('dashboard/branch/deliveries', [DashboardController::class, 'branchDeliveries']);
    Route::get('dashboard/branch/deliveries-management', [DashboardController::class, 'branchDeliveries']);
    Route::get('dashboard/branch-deliveries', [DashboardController::class, 'branchDeliveries']);

    // Universal / Auto-detecting Deliveries Route
    Route::get('dashboard/deliveries-management', [DashboardController::class, 'deliveriesManagement']);
    Route::get('dashboard/deliveries', [DashboardController::class, 'deliveriesManagement']);

    // Super Admin / HQ Drivers Management (Global across all branches)
    Route::get('dashboard/hq/drivers', [DashboardController::class, 'hqDrivers']);
    Route::get('dashboard/hq/drivers-management', [DashboardController::class, 'hqDrivers']);
    Route::get('dashboard/hq-drivers', [DashboardController::class, 'hqDrivers']);
    Route::get('dashboard/drivers-management', [DashboardController::class, 'hqDrivers']);

    // Super Admin CRM Management
    Route::get('dashboard/crm-overview', [DashboardController::class, 'crmOverview']);
    Route::get('dashboard/crm', [DashboardController::class, 'crmOverview']);
    Route::get('dashboard/hq/crm', [DashboardController::class, 'crmOverview']);
    Route::get('dashboard/crm-management', [DashboardController::class, 'crmOverview']);

    // Income Reports & Analytics (Branch & HQ)
    Route::get('dashboard/branch/income-reports', [DashboardController::class, 'incomeReports']);
    Route::get('dashboard/income-reports', [DashboardController::class, 'incomeReports']);
    Route::get('dashboard/reports/income-analytics', [DashboardController::class, 'incomeReports']);
    Route::get('dashboard/income-analytics', [DashboardController::class, 'incomeReports']);

    // KDS Overview & Chef Dashboard (Station Tracking & Live Orders)
    Route::get('dashboard/kds-overview', [DashboardController::class, 'kdsOverview']);
    Route::get('dashboard/kds', [DashboardController::class, 'kdsOverview']);
    Route::get('dashboard/branch/kds', [DashboardController::class, 'kdsOverview']);
    Route::get('dashboard/chef', [DashboardController::class, 'kdsOverview']);
    Route::get('dashboard/chef-overview', [DashboardController::class, 'kdsOverview']);
    Route::get('dashboard/chef/kds', [DashboardController::class, 'kdsOverview']);
    Route::get('kds/overview', [DashboardController::class, 'kdsOverview']);

    // Super Admin Marketing Campaign Hub & Communications
    Route::get('dashboard/marketing-overview', [DashboardController::class, 'marketingOverview']);
    Route::get('dashboard/marketing', [DashboardController::class, 'marketingOverview']);
    Route::get('dashboard/hq/marketing', [DashboardController::class, 'marketingOverview']);
    Route::get('dashboard/campaigns-hub', [DashboardController::class, 'marketingOverview']);
    Route::post('dashboard/marketing/create-flow', [CampaignAutomationFlowController::class, 'store']);
    Route::post('marketing/create-flow', [CampaignAutomationFlowController::class, 'store']);
    Route::get('marketing/flows/{campaign_automation_flow}', [CampaignAutomationFlowController::class, 'show']);
    Route::get('marketing/create-flow/{campaign_automation_flow}', [CampaignAutomationFlowController::class, 'show']);

    // Super Admin Digital Signage Hub & In-Store Screens
    Route::get('dashboard/signage-overview', [DashboardController::class, 'signageOverview']);
    Route::get('dashboard/signage', [DashboardController::class, 'signageOverview']);
    Route::get('dashboard/hq/signage', [DashboardController::class, 'signageOverview']);
    Route::get('dashboard/signage/screens/{digital_screen}', [DigitalScreenController::class, 'show']);
    Route::get('signage/screens/{digital_screen}', [DigitalScreenController::class, 'show']);
    Route::get('signage/groups', [ScreenGroupController::class, 'index']);
    Route::get('signage/groups/{screen_group}', [ScreenGroupController::class, 'show']);
    Route::post('screen-groups/{screen_group}/assign-screens', [ScreenGroupController::class, 'assignScreens']);
    Route::post('screen-groups/{screen_group}/sync-screens', [ScreenGroupController::class, 'syncScreens']);
    Route::get('signage/contents', [SignageContentController::class, 'index']);
    Route::get('signage/contents/{signage_content}', [SignageContentController::class, 'show']);
    Route::post('signage/publish-content', [SignageContentController::class, 'publish']);
    Route::post('signage-contents/publish', [SignageContentController::class, 'publish']);
    Route::post('dashboard/signage/add-content', [SignageContentController::class, 'publish']);

    Route::get('dashboard/staff-overview', [DashboardController::class, 'staffOverview']);

    // Public Authentication & OTP Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('verify-otp', [AuthController::class, 'verifyRegistrationOtp']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('login', [AuthController::class, 'login']);

        // Phone OTP Auth Endpoints
        Route::post('phone/send-otp', [AuthController::class, 'sendPhoneOtp']);
        Route::post('phone/verify-otp', [AuthController::class, 'verifyPhoneOtp']);
        Route::post('phone/resend-otp', [AuthController::class, 'resendPhoneOtp']);


        // Protected Auth Endpoints
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('fcm-token', [AuthController::class, 'updateFcmToken']);
        });
    });

    // Public Browsing, Branch Selection, Cart & Guest Checkout Routes
    Route::get('restaurants', [RestaurantController::class, 'index']);
    Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show']);
    Route::get('/branches/overview', [BranchController::class, 'overview']);
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('/subcategories', [SubcategoryController::class, 'index']);
    Route::apiResource('subcategories', SubcategoryController::class)->except(['index']);
    Route::get('menu-items', [MenuItemController::class, 'index']);
    Route::get('menu-items/{menuItem}', [MenuItemController::class, 'show']);

    // Public Cart & Checkout (Guest Session & Customer Support)
    Route::get('carts', [CartController::class, 'index']);
    Route::get('carts/{cart}', [CartController::class, 'show']);
    Route::post('carts', [CartController::class, 'store']);
    Route::post('cart-items', [CartItemController::class, 'store']);
    Route::put('cart-items/{cartItem}', [CartItemController::class, 'update']);
    Route::delete('cart-items/{cartItem}', [CartItemController::class, 'destroy']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('coupons/apply', [CouponController::class, 'apply']);
    Route::post('delivery-areas/check', [DeliveryAreaController::class, 'checkPostcode']);
    Route::get('payment-gateways/active', [PaymentGatewayController::class, 'activeGateways']);
    Route::match(['get', 'post'], 'delivery-fee-tiers/match', [DeliveryFeeTierController::class, 'matchDistance']);


    // Protected API Endpoints (Requires Sanctum Token)
    Route::middleware('auth:sanctum')->group(function () {

        // User Management & CSV Import/Export
        Route::get('users/sample-csv', [UserController::class, 'sampleCsv']);
        Route::get('users/export-csv', [UserController::class, 'exportCsv']);
        Route::post('users/import-csv', [UserController::class, 'importCsv']);
        Route::get('users/roles-and-types', [UserController::class, 'helperOptions']);
        Route::match(['post', 'patch', 'put'], 'users/{user}/status', [UserController::class, 'updateStatus']);
        Route::apiResource('users', UserController::class);
        Route::post('/profile_update', [ProfileController::class, 'updateProfile']);
        Route::apiResource('user-addresses', UserAddressController::class);

        // Shopping Cart, Orders & Payments
        Route::apiResource('carts', CartController::class)->except(['index', 'store','show']);
        Route::apiResource('cart-items', CartItemController::class)->except(['store', 'update', 'destroy']);
        Route::post('orders/{order}/assign-driver', [OrderController::class, 'assignDriver']);
        Route::post('orders/{order}/approve', [OrderController::class, 'approve']);
        Route::post('orders/{order}/reject', [OrderController::class, 'reject']);
        Route::apiResource('orders', OrderController::class)->except(['store']);
        Route::apiResource('order-items', OrderItemController::class);
        Route::post('payments/{payment}/refund', [PaymentController::class, 'processRefund']);
        Route::apiResource('payments', PaymentController::class);
        Route::apiResource('coupons', CouponController::class);
        Route::get('loyalty-points/balance', [LoyaltyPointController::class, 'userBalance']);
        Route::apiResource('loyalty-points', LoyaltyPointController::class);
        Route::get('reviews/summary', [ReviewController::class, 'summary']);
        Route::apiResource('reviews', ReviewController::class);
        Route::patch('table-reservations/{table_reservation}/status', [TableReservationController::class, 'updateStatus']);
        Route::apiResource('table-reservations', TableReservationController::class);
        Route::post('pages', [PageController::class, 'store']);
        Route::put('pages/{page_id}', [PageController::class, 'update']);
        Route::delete('pages/{page_id}', [PageController::class, 'destroy']);
        Route::delete('account-delete', [DeleteUsersController::class, 'destroy']);
        Route::apiResource('faqs', FaqController::class);
        Route::post('/change-password', [DeleteUsersController::class, 'changePassword']);
        Route::post('/drivers/location', [DriverLocationController::class, 'update'])->name('drivers.location.update');

        // Notification Center & Notification Settings
        Route::get('notifications/summary', [NotificationController::class, 'summary']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::patch('notifications/{notification}/toggle-read', [NotificationController::class, 'toggleRead']);
        Route::apiResource('notifications', NotificationController::class);
        Route::apiResource('notification-settings', NotificationSettingController::class);

        // Real-time Chat & Messaging System
        Route::get('chat/contacts', [ChatController::class, 'contacts']);
        Route::get('conversations', [ChatController::class, 'index']);
        Route::post('conversations', [ChatController::class, 'store']);
        Route::get('conversations/{conversation}', [ChatController::class, 'show']);
        Route::post('conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
        Route::post('conversations/{conversation}/read', [ChatController::class, 'markAsRead']);

        // Role & Permission Protected Operations
        Route::middleware('role:super_admin,admin,hq_admin,branch_admin,branch_manager,cashier,staff,driver')->group(function () {
            Route::post('roles/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);
            Route::post('roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
            Route::apiResource('roles', RoleController::class);
            Route::get('permissions/modules', [PermissionController::class, 'modules']);
            Route::post('permissions/bulk', [PermissionController::class, 'bulkStore']);
            Route::apiResource('permissions', PermissionController::class);
            Route::apiResource('hq-admins', HqAdminController::class);
            Route::patch('branch-admins/{branch_admin}/toggle-status', [BranchAdminController::class, 'toggleStatus']);
            Route::apiResource('branch-admins', BranchAdminController::class);
            Route::get('/staff/export', [StaffController::class, 'export']);
            Route::get('/staff/overview', [StaffController::class, 'overview']);
            Route::get('/staff/management-summary/{branch_id}', [StaffController::class, 'managementSummary']);
            Route::get('/staff/cash-reconciliation/{branch_id}', [StaffController::class, 'cashReconciliationOverview']);
            Route::post('/staff/cash-reconciliation/submit', [StaffController::class, 'submitCashReconciliation']);
            Route::get('/cash-reconciliation/{branch_id}', [StaffController::class, 'cashReconciliationOverview']);
            Route::post('/cash-reconciliation/submit', [StaffController::class, 'submitCashReconciliation']);
            Route::get('/staff/earnings', [StaffPayoutController::class, 'staffEarnings']);
            Route::get('/staff/salary-history', [StaffPayoutController::class, 'salaryHistory']);
            Route::apiResource('staff', StaffController::class);
            Route::post('staff-attendance/clock-in', [StaffAttendanceController::class, 'clockIn']);
            Route::post('staff-attendance/clock-out', [StaffAttendanceController::class, 'clockOut']);
            Route::post('staff-attendance/{staff_attendance}/clock-out', [StaffAttendanceController::class, 'clockOut']);
            Route::apiResource('staff-attendance', StaffAttendanceController::class);
            Route::apiResource('restaurants', RestaurantController::class)->except(['index', 'show']);
            Route::apiResource('branches', BranchController::class)->except('index','show');
            Route::get('branch-settings/branch/{branch}', [BranchSettingController::class, 'getByBranch']);
            Route::apiResource('branch-settings', BranchSettingController::class);
            Route::apiResource('delivery-areas', DeliveryAreaController::class);
            Route::patch('restaurant-tables/{restaurant_table}/status', [RestaurantTableController::class, 'updateStatus']);
            Route::post('restaurant-tables/{restaurant_table}/qr-code', [RestaurantTableController::class, 'generateQrCode']);
            Route::apiResource('restaurant-tables', RestaurantTableController::class);

            Route::apiResource('categories', CategoryController::class)->except(['index']);

            Route::apiResource('menu-items', MenuItemController::class)->except(['index', 'show']);
            Route::apiResource('item-sizes', ItemSizeController::class);
            Route::post('cooking-preferences/bulk', [CookingPreferenceController::class, 'bulkStore']);
            Route::apiResource('cooking-preferences', CookingPreferenceController::class);
            Route::post('spice-levels/bulk', [SpiceLevelController::class, 'bulkStore']);
            Route::apiResource('spice-levels', SpiceLevelController::class);
            Route::post('toppings/bulk', [ToppingController::class, 'bulkStore']);
            Route::apiResource('toppings', ToppingController::class);
            Route::post('recipes/{recipe}/ingredients', [RecipeController::class, 'addIngredient']);
            Route::delete('recipes/{recipe}/ingredients/{ingredient}', [RecipeController::class, 'removeIngredient']);
            Route::apiResource('recipes', RecipeController::class);

            Route::patch('payment-gateways/{payment_gateway}/toggle-status', [PaymentGatewayController::class, 'toggleStatus']);
            Route::post('payment-gateways/{payment_gateway}/test-connection', [PaymentGatewayController::class, 'testConnection']);
            Route::apiResource('payment-gateways', PaymentGatewayController::class);
            Route::post('integrations/{integration}/test-webhook', [IntegrationController::class, 'testWebhook']);
            Route::apiResource('integrations', IntegrationController::class);
            Route::post('system-settings/bulk', [SystemSettingController::class, 'bulkUpdate']);
            Route::get('system-settings/key/{key}', [SystemSettingController::class, 'getByKey']);
            Route::apiResource('system-settings', SystemSettingController::class);
            Route::get('activity-logs/stats', [ActivityLogController::class, 'stats']);
            Route::get('activity-logs/modules', [ActivityLogController::class, 'modules']);
            Route::apiResource('activity-logs', ActivityLogController::class);
            Route::get('audit-logs/stats', [AuditLogController::class, 'stats']);
            Route::get('audit-logs/modules', [AuditLogController::class, 'modules']);
            Route::apiResource('audit-logs', AuditLogController::class);
            Route::get('report-exports/{report_export}/download', [ReportExportController::class, 'download']);
            Route::post('report-exports/generate', [ReportExportController::class, 'generate']);
            Route::apiResource('report-exports', ReportExportController::class);
            Route::post('saved-reports/{saved_report}/run', [SavedReportController::class, 'run']);
            Route::post('saved-reports/{saved_report}/duplicate', [SavedReportController::class, 'duplicate']);
            Route::apiResource('saved-reports', SavedReportController::class);
            Route::patch('scheduled-reports/{scheduled_report}/toggle-status', [ScheduledReportController::class, 'toggleStatus']);
            Route::post('scheduled-reports/{scheduled_report}/run-now', [ScheduledReportController::class, 'runNow']);
            Route::apiResource('scheduled-reports', ScheduledReportController::class);
            Route::get('ai-insights/dashboard', [AiInsightController::class, 'dashboard']);
            Route::post('ai-insights/refresh', [AiInsightController::class, 'refresh']);
            Route::apiResource('ai-insights', AiInsightController::class);
            Route::apiResource('ai-recommendations', AiRecommendationController::class);

        });

        // POS & Kitchen Operations (KDS)
        Route::middleware('role:super_admin,hq_admin,branch_admin,cashier,chef,waiter')->group(function () {
            Route::apiResource('pos-sessions', PosSessionController::class);
            Route::apiResource('kitchen-stations', KitchenStationController::class);
            Route::apiResource('kitchen-orders', KitchenOrderController::class);
        });

        // Inventory Management
        Route::middleware('role:super_admin,hq_admin,branch_admin,inventory_manager')->group(function () {
            Route::apiResource('inventory-categories', InventoryCategoryController::class);
            Route::get('inventory-items/summary', [InventoryItemController::class, 'summary']);
            Route::get('inventory-items/analytics', [InventoryItemController::class, 'analytics']);
            Route::get('inventory-items/export', [InventoryItemController::class, 'export']);
            Route::post('inventory-items/{inventoryItem}/distribute', [InventoryItemController::class, 'distribute']);
            Route::post('inventory-items/{inventoryItem}', [InventoryItemController::class, 'update']);
            Route::apiResource('inventory-items', InventoryItemController::class);
            Route::apiResource('inventory-transactions', InventoryTransactionController::class);
            Route::apiResource('suppliers', SupplierController::class);
            Route::post('stock-conversions/calculate', [StockConversionController::class, 'calculate']);
            Route::post('stock-conversions/convert', [StockConversionController::class, 'convert']);
            Route::apiResource('stock-conversions', StockConversionController::class);
        });

        // Delivery & Driver Fleet
        Route::middleware('role:super_admin,hq_admin,branch_admin,driver,admin,staff')->group(function () {
            // Delivery Fee Tiers (Admin Dashboard - Distance-based rates)
            Route::apiResource('delivery-fee-tiers', DeliveryFeeTierController::class);

            // Driver Shifts (Clock in / Clock out)
            Route::post('drivers/clock-in', [DriverShiftController::class, 'clockIn']);
            Route::post('drivers/clock-out', [DriverShiftController::class, 'clockOut']);
            Route::get('drivers/current-shift', [DriverShiftController::class, 'currentShift']);
            Route::get('driver-shifts', [DriverShiftController::class, 'index']);

            // Driver Payouts & Earnings
            Route::get('driver-payouts', [DriverPayoutController::class, 'index']);
            Route::post('driver-payouts/calculate', [DriverPayoutController::class, 'calculate']);
            Route::post('driver-payouts/{driver_payout}/process', [DriverPayoutController::class, 'process']);
            Route::get('drivers/earnings', [DriverPayoutController::class, 'driverEarnings']);
            Route::post('drivers/stripe-onboard', [DriverPayoutController::class, 'stripeOnboard']);

            // Staff Payouts & Salary (Manual, approval-gated, Stripe Connect)
            Route::get('staff-payouts', [StaffPayoutController::class, 'index']);
            Route::post('staff-payouts/calculate', [StaffPayoutController::class, 'calculate']);
            Route::get('staff-payouts/payroll-review', [StaffPayoutController::class, 'payrollReview']);
            Route::post('staff-payouts/approve', [StaffPayoutController::class, 'approve']);
            Route::post('staff-payouts/approve-batch', [StaffPayoutController::class, 'approveBatch']);
            Route::post('staff-payouts/{staff_payout}/process', [StaffPayoutController::class, 'process']);
            Route::post('staff-payouts/stripe-onboard', [StaffPayoutController::class, 'stripeOnboard']);
            Route::post('staff-payouts/stripe-status', [StaffPayoutController::class, 'stripeStatus']);

            Route::get('drivers/upcoming-requests', [DriverController::class, 'upcomingRequests']);
            Route::get('drivers/my-deliveries', [DriverController::class, 'myDeliveries']);
            Route::post('drivers/orders/{order}/accept', [DriverController::class, 'acceptOrder']);
            Route::post('drivers/orders/{order}/decline', [DriverController::class, 'declineOrder']);
            Route::post('drivers/fcm-token', [DriverController::class, 'updateFcmToken']);
            Route::post('drivers/submit-kyc', [DriverController::class, 'submitKyc']);
            Route::put('drivers/{driver}/status', [DriverController::class, 'updateStatus']);
            Route::put('drivers/{driver}/kyc-status', [DriverController::class, 'updateKycStatus']);
            Route::apiResource('drivers', DriverController::class);
            Route::apiResource('deliveries', DeliveryController::class);
        });

        // CRM & Marketing
        Route::middleware('role:super_admin,admin,hq_admin,branch_admin,branch_manager,cashier,staff,marketing_manager')->group(function () {
            Route::get('call-logs/overview', [CallLogController::class, 'overview']);
            Route::get('call-logs/stats', [CallLogController::class, 'stats']);
            Route::get('call-logs/converted-orders', [CallLogController::class, 'convertedOrders']);
            Route::get('call-logs/history', [CallLogController::class, 'history']);
            Route::post('call-logs/{call_log}/convert-order', [CallLogController::class, 'convertOrder']);
            Route::post('call-logs/{call_log}/callback', [CallLogController::class, 'logCallback']);
            Route::apiResource('call-logs', CallLogController::class);
            Route::post('customer-tags/bulk', [CustomerTagController::class, 'bulkStore']);
            Route::apiResource('customer-tags', CustomerTagController::class);
            Route::apiResource('customer-notes', CustomerNoteController::class);
            Route::get('customer-segments/{customer_segment}/customers', [CustomerSegmentController::class, 'getCustomers']);
            Route::apiResource('customer-segments', CustomerSegmentController::class);
            Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send']);
            Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel']);
            Route::apiResource('campaigns', CampaignController::class);
            Route::post('campaign-recipients/bulk', [CampaignRecipientController::class, 'bulkStore']);
            Route::patch('campaign-recipients/{campaign_recipient}/mark-sent', [CampaignRecipientController::class, 'markAsSent']);
            Route::apiResource('campaign-recipients', CampaignRecipientController::class);
            Route::post('campaign-messages/bulk', [CampaignMessageController::class, 'bulkStore']);
            Route::apiResource('campaign-messages', CampaignMessageController::class);
            Route::patch('campaign-automation-flows/{campaign_automation_flow}/toggle-status', [CampaignAutomationFlowController::class, 'toggleStatus']);
            Route::apiResource('campaign-automation-flows', CampaignAutomationFlowController::class);
            Route::get('campaign-statistics/campaign/{campaign}', [CampaignStatisticController::class, 'getByCampaign']);
            Route::post('campaign-statistics/campaign/{campaign}/recalculate', [CampaignStatisticController::class, 'recalculate']);
            Route::apiResource('campaign-statistics', CampaignStatisticController::class);
        });

        // Digital Signage
        Route::middleware('role:super_admin,hq_admin,branch_admin')->group(function () {
            Route::post('digital-screens/{digital_screen}/sync', [DigitalScreenController::class, 'sync']);
            Route::apiResource('digital-screens', DigitalScreenController::class);
            Route::post('screen-groups/{screen_group}/sync-screens', [ScreenGroupController::class, 'syncScreens']);
            Route::post('screen-groups/{screen_group}/assign-screens', [ScreenGroupController::class, 'assignScreens']);
            Route::apiResource('screen-groups', ScreenGroupController::class);
            Route::patch('signage-contents/{signage_content}/toggle-status', [SignageContentController::class, 'toggleStatus']);
            Route::apiResource('signage-contents', SignageContentController::class);
            Route::post('screen-playlists/{screen_playlist}/sync-items', [ScreenPlaylistController::class, 'syncItems']);
            Route::post('screen-playlists/{screen_playlist}/add-item', [ScreenPlaylistController::class, 'addItem']);
            Route::apiResource('screen-playlists', ScreenPlaylistController::class);
            Route::patch('screen-schedules/{screen_schedule}/toggle-status', [ScreenScheduleController::class, 'toggleStatus']);
            Route::apiResource('screen-schedules', ScreenScheduleController::class);
        });

        // Notifications
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::apiResource('notifications', NotificationController::class);
        Route::apiResource('notification-settings', NotificationSettingController::class);

        //Wishlist
        Route::get('/my-wishlist', [WishlistController::class, 'myWishlist']);
        Route::post('/toggle-wishlist', [WishlistController::class, 'toggle']);

        //POS (Branch Admin/ Cashier)
        Route::get('/category-list', [POSController::class, 'getCategoryList']);
        Route::get('/menu-items/{categoryID}/{branchID}', [POSController::class, 'getMenuItem']);
        Route::get('/menu-items/{menuItem}', [POSController::class, 'showMenuItem']);
        Route::post('/pos/add-to-cart', [POSController::class, 'addToCart']);
        Route::get('/pos/cart-items', [POSController::class, 'getCartItems']);
        Route::post('/pos/update-cart-item/{cartItemId}', [POSController::class, 'updateCartItem']);
        Route::post('/pos/checkout', [POSController::class, 'checkout']);

    });

    //Stripe
    Route::get('/order/success', [StripeController::class, 'OrderSuccess']);
    Route::get('/order/cancel', [StripeController::class, 'OrderCancel']);
    Route::post('/order/webhook-handle', [StripeController::class, 'handleWebhook']);

    //Onboarding Webhook
    Route::post('/onboarding/webhook', [DriverPayoutController::class, 'handleWebhook']);
    Route::post('/staff-onboarding/webhook', [StaffPayoutController::class, 'handleWebhook']);

    // Twilio Public Voice & Callback Webhooks
    Route::prefix('twilio')->group(function () {
        Route::post('voice', [TwilioWebhookController::class, 'voice']);
        Route::match(['get', 'post'], 'status-callback', [TwilioWebhookController::class, 'statusCallback']);
        Route::post('recording-callback', [TwilioWebhookController::class, 'recordingCallback']);
    });

    // Twilio Authenticated Click-to-Call
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('twilio/make-call', [TwilioWebhookController::class, 'makeCall']);
    });

});

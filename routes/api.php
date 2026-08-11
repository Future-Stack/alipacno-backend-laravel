<?php

use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Subcategory\SubcategoryController;
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

use App\Http\Controllers\CallLogController;
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

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Page\PageController;

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

      // Public Pages
        Route::get('pages', [PageController::class, 'index']);
        Route::get('pages/{page_id}', [PageController::class, 'show']);

    // Executive & Operational Dashboard Endpoints
    Route::get('dashboard/hq-overview', [DashboardController::class, 'hqOverview']);
    Route::get('dashboard/order-management', [DashboardController::class, 'orderManagement']);
    Route::get('dashboard/crm-overview', [DashboardController::class, 'crmOverview']);
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
        });
    });

    // Public Browsing, Branch Selection, Cart & Guest Checkout Routes
    Route::get('restaurants', [RestaurantController::class, 'index']);
    Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show']);
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('/subcategories', [SubcategoryController::class, 'index']);
    Route::apiResource('subcategories', SubcategoryController::class)->except(['index']);
    Route::get('menu-items', [MenuItemController::class, 'index']);
    Route::get('menu-items/{menuItem}', [MenuItemController::class, 'show']);

    // Public Cart & Checkout (Guest Session & Customer Support)
    Route::get('carts', [CartController::class, 'index']);
    Route::post('carts', [CartController::class, 'store']);
    Route::post('cart-items', [CartItemController::class, 'store']);
    Route::put('cart-items/{cartItem}', [CartItemController::class, 'update']);
    Route::delete('cart-items/{cartItem}', [CartItemController::class, 'destroy']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('coupons/apply', [CouponController::class, 'apply']);
    Route::post('delivery-areas/check', [DeliveryAreaController::class, 'checkPostcode']);
    Route::get('payment-gateways/active', [PaymentGatewayController::class, 'activeGateways']);

    // Protected API Endpoints (Requires Sanctum Token)
    Route::middleware('auth:sanctum')->group(function () {

        // User & Addresses
        Route::apiResource('users', UserController::class);
        Route::post('/profile_update', [ProfileController::class, 'updateProfile']);
        Route::apiResource('user-addresses', UserAddressController::class);

        // Shopping Cart, Orders & Payments
        Route::apiResource('carts', CartController::class)->except(['index', 'store']);
        Route::apiResource('cart-items', CartItemController::class)->except(['store', 'update', 'destroy']);
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
        // Super Admin Only Operations
        Route::middleware('role:super_admin')->group(function () {

            // Pages (Super Admin Only)
            Route::post('pages', [PageController::class, 'store']);
            Route::put('pages/{page_id}', [PageController::class, 'update']);
            Route::delete('pages/{page_id}', [PageController::class, 'destroy']);

        });

        // Role & Permission Protected Operations
        Route::middleware('role:super_admin,hq_admin,branch_admin')->group(function () {
            Route::post('roles/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);
            Route::post('roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
            Route::apiResource('roles', RoleController::class);
            Route::get('permissions/modules', [PermissionController::class, 'modules']);
            Route::post('permissions/bulk', [PermissionController::class, 'bulkStore']);
            Route::apiResource('permissions', PermissionController::class);
            Route::apiResource('hq-admins', HqAdminController::class);
            Route::patch('branch-admins/{branch_admin}/toggle-status', [BranchAdminController::class, 'toggleStatus']);
            Route::apiResource('branch-admins', BranchAdminController::class);
            Route::apiResource('staff', StaffController::class);
            Route::post('staff-attendance/clock-in', [StaffAttendanceController::class, 'clockIn']);
            Route::post('staff-attendance/{staff_attendance}/clock-out', [StaffAttendanceController::class, 'clockOut']);
            Route::apiResource('staff-attendance', StaffAttendanceController::class);
            Route::apiResource('restaurants', RestaurantController::class)->except(['index', 'show']);
            Route::apiResource('branches', BranchController::class);
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
            Route::apiResource('inventory-items', InventoryItemController::class);
            Route::apiResource('inventory-transactions', InventoryTransactionController::class);
            Route::apiResource('suppliers', SupplierController::class);
            Route::post('stock-conversions/calculate', [StockConversionController::class, 'calculate']);
            Route::apiResource('stock-conversions', StockConversionController::class);
        });

        // Delivery & Driver Fleet
        Route::middleware('role:super_admin,hq_admin,branch_admin,driver')->group(function () {
            Route::put('drivers/{driver}/status', [DriverController::class, 'updateStatus']);
            Route::apiResource('drivers', DriverController::class);
            Route::apiResource('deliveries', DeliveryController::class);
        });

        // CRM & Marketing
        Route::middleware('role:super_admin,hq_admin,branch_admin,marketing_manager')->group(function () {
            Route::get('call-logs/stats', [CallLogController::class, 'stats']);
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
    });
});


<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    UserController,
    CategoryController,
    ProductController,
    OrderController,
    OrderItemController,
    CommentController,
    PaymentController,
    ReviewController,
    BannerController,
    ChatbotController,
    CouponController,
    DeliveryController,
    NotificationController,
    RevenueExportController,
    VNPayController,
    ForgotPasswordController
};

use App\Http\Controllers\MomoController;

// ⚠️ chỉ tạm dùng để test
Route::get('/users', [UserController::class, 'index']);

/*
|--------------------------------------------------------------------------
| AUTH (Public)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login'])->name('login');

Route::post('/forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| PUBLIC APIs (Không cần đăng nhập)
|--------------------------------------------------------------------------
*/
Route::get('/products/all', [ProductController::class, 'getAllWithoutPagination']);
Route::get('/products',                        [ProductController::class, 'index']);
Route::get('/products/{id}',                   [ProductController::class, 'show']);
Route::get('/categories',                      [CategoryController::class, 'index']);
Route::get('/banners',                         [BannerController::class, 'index']);
Route::get('/coupons',                         [CouponController::class, 'index']);
Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);
// Lấy theo id
Route::get('/categories/{id}/products', [ProductController::class, 'getByCategory']);

// Lấy theo slug
Route::get('/category-slug/{slug}/products', [CategoryController::class, 'productsBySlug']);

Route::post('/chat', [ChatbotController::class, 'chat']);

Route::prefix('vnpay')->group(function () {
    Route::post('/create-payment', [VNPayController::class, 'createPayment']);
    Route::get('/return', [VNPayController::class, 'vnpayReturn']);
});

// routes/api.php
Route::post('/momo/create-payment', [MomoController::class, 'createPayment']);
Route::post('/momo/notify', [MomoController::class, 'notify']);
Route::get('/momo/status/{orderId}', [MomoController::class, 'transactionStatus']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER APIs (Cần Sanctum token)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/me',               [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::put('/update-profile',   [AuthController::class, 'updateProfile']);

    // Orders (người dùng thao tác đơn hàng của mình)
    Route::get('/orders/my-orders',   [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}',        [OrderController::class, 'show']);
    Route::post('/orders',            [OrderController::class, 'store']);
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);

    // Reviews & Comments
    Route::apiResource('reviews', ReviewController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('comments', CommentController::class)->only(['store', 'update', 'destroy']);

    // Payments
    Route::apiResource('payments', PaymentController::class)->only(['store', 'show']);

    // Notifications & Delivery tracking
    Route::get('/delivery/{id}', [DeliveryController::class, 'show']);


    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    Route::post('apply-coupon', [CouponController::class, 'apply']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::post('/notifications', [NotificationController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| ADMIN APIs (Cần quyền quản trị)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {

    // ========== USER MANAGEMENT ==========
    Route::apiResource('users', UserController::class);
    Route::post('users/bulk-delete', [UserController::class, 'bulkDelete']);

    // ========== PRODUCT MANAGEMENT ==========
    Route::apiResource('products', ProductController::class);
    Route::put('products/{id}/stock', [ProductController::class, 'updateStock']);
    Route::put('products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);
    Route::post('products/bulk-delete', [ProductController::class, 'bulkDelete']);
    Route::post('products/import', [ProductController::class, 'import']);

    // ========== ORDER MANAGEMENT ==========
    Route::get('orders/statistics', [OrderController::class, 'statistics']);
    Route::apiResource('orders', OrderController::class);
    Route::put('orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('orders/bulk-delete', [OrderController::class, 'bulkDelete']);

    // ========== CATEGORY MANAGEMENT ==========
    Route::apiResource('categories', CategoryController::class);

    // ========== BANNERS, COUPONS, DELIVERY ==========
    Route::apiResource('banners', BannerController::class)->except(['index']);
    Route::apiResource('coupons', CouponController::class);
    Route::apiResource('delivery', DeliveryController::class)->except(['show']);

    Route::get('revenue/export', [RevenueExportController::class, 'export']);

    Route::get('reviews', [ReviewController::class, 'all']);
    Route::put('reviews/{id}/toggle', [ReviewController::class, 'toggleVisibility']);

    Route::get('notifications/all', [NotificationController::class, 'allNotifications']); // Lấy tất cả thông báo
    Route::apiResource('notifications', NotificationController::class)->except(['index', 'show']); // Tạo, sửa, xóa
    Route::put('notifications/{id}/toggle-read', [NotificationController::class, 'toggleReadStatus']); // Chuyển đổi trạng thái đọc
    Route::post('notifications/send-to-all', [NotificationController::class, 'sendToAllUsers']); // Gửi thông báo tới tất cả người dùng
});

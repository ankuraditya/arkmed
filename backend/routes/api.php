<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\MedicineController as AdminMedicineController;
use App\Http\Controllers\Api\Admin\MedicineImportController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\Admin\PrescriptionController as AdminPrescriptionController;
use App\Http\Controllers\Api\Admin\PrescriptionFileController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\OrderWhatsAppController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PrescriptionUploadController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/ready', ReadinessController::class);
    Route::get('/settings/public', PublicSettingsController::class)->middleware('throttle:60,1');
    Route::get('/home', HomeController::class)->middleware('throttle:60,1');
    Route::get('/pages/{slug}', [PageController::class, 'show'])->middleware('throttle:60,1');
    Route::post('/enquiries', [EnquiryController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/medicines', [MedicineController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/medicines/{medicine:slug}', [MedicineController::class, 'show'])->middleware('throttle:60,1');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/orders/track', OrderTrackingController::class)->middleware('throttle:5,1');
    Route::get('/orders/{publicId}/whatsapp', OrderWhatsAppController::class)->middleware(['signed', 'throttle:20,1'])->name('orders.whatsapp');
    Route::post('/prescription-uploads', [PrescriptionUploadController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
    Route::prefix('admin')->middleware(['auth:sanctum', 'active.admin'])->group(function (): void {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::put('/profile', [AdminAuthController::class, 'updateProfile']);
        Route::put('/password', [AdminAuthController::class, 'updatePassword'])->middleware('throttle:5,1');
        Route::get('/dashboard', AdminDashboardController::class);
        Route::middleware('role:super_admin,pharmacy_admin')->group(function (): void {
            Route::apiResource('medicines', AdminMedicineController::class);
            Route::post('/medicines-bulk', [AdminMedicineController::class, 'bulk']);
            Route::post('/medicines/{id}/restore', [AdminMedicineController::class, 'restore']);
            Route::post('/medicines/{medicine}/duplicate', [AdminMedicineController::class, 'duplicate']);
            Route::apiResource('categories', AdminCategoryController::class)->except('show');
            Route::get('/medicine-imports', [MedicineImportController::class, 'index']);
            Route::get('/medicine-imports/template', [MedicineImportController::class, 'template']);
            Route::post('/medicine-imports/preview', [MedicineImportController::class, 'preview']);
            Route::post('/medicine-imports/{publicId}/commit', [MedicineImportController::class, 'commit']);
            Route::get('/medicine-imports/{publicId}', [MedicineImportController::class, 'show']);
            Route::get('/medicine-imports/{publicId}/errors', [MedicineImportController::class, 'errors']);
        });
        Route::middleware('role:super_admin,pharmacy_admin,order_staff')->group(function (): void {
            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::get('/orders-export', [AdminOrderController::class, 'export'])->middleware('throttle:5,1');
            Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
            Route::put('/orders/{order}', [AdminOrderController::class, 'update']);
            Route::get('/enquiries', [AdminEnquiryController::class, 'index']);
            Route::get('/enquiries/{enquiry}', [AdminEnquiryController::class, 'show']);
            Route::put('/enquiries/{enquiry}', [AdminEnquiryController::class, 'update']);
        });
        Route::middleware('role:super_admin,content_manager')->group(function (): void {
            Route::apiResource('pages', AdminPageController::class)->except('show');
            Route::post('/pages/{page}/duplicate', [AdminPageController::class, 'duplicate']);
            Route::apiResource('banners', AdminBannerController::class)->except('show');
            Route::post('/banners/{banner}/duplicate', [AdminBannerController::class, 'duplicate']);
        });
        Route::middleware('role:super_admin,pharmacy_admin,content_manager')->group(function (): void {
            Route::get('/media', [MediaController::class, 'index']);
            Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:20,1');
            Route::put('/media/{medium}', [MediaController::class, 'update']);
            Route::delete('/media/{medium}', [MediaController::class, 'destroy']);
        });
        Route::middleware('role:super_admin,pharmacy_admin,prescription_reviewer')->group(function (): void {
            Route::get('/prescriptions', [AdminPrescriptionController::class, 'index']);
            Route::get('/prescriptions/{prescription}', [AdminPrescriptionController::class, 'show']);
            Route::put('/prescriptions/{prescription}', [AdminPrescriptionController::class, 'update']);
            Route::get('/prescription-files/{file}', PrescriptionFileController::class)->middleware('throttle:30,1');
        });
        Route::middleware('role:super_admin')->group(function (): void {
            Route::get('/settings', [AdminSettingController::class, 'index']);
            Route::put('/settings', [AdminSettingController::class, 'update']);
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::get('/audit-logs', AuditLogController::class);
            Route::get('/audit-logs-export', [AuditLogController::class, 'export'])->middleware('throttle:5,1');
        });
    });
});

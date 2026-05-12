<?php

use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Admin\AiMonitoringController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DataManagementController;
use App\Http\Controllers\AI\BookAssistantController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Customer\DataExportController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/books', [CatalogController::class, 'index'])->name('books.index');
Route::get('/books/{book:slug}', [CatalogController::class, 'show'])->name('books.show');

Route::get('/ai/book-assistant', [AiAssistantController::class, 'index'])->name('ai.assistant.index');
Route::post('/ai/book-assistant', [AiAssistantController::class, 'store'])
    ->middleware('throttle:ai-public')
    ->name('ai.assistant.store');
Route::get('/ai-assistant', [BookAssistantController::class, 'index'])->name('ai.book-assistant.index');
Route::post('/ai-assistant/messages', [BookAssistantController::class, 'store'])
    ->middleware('throttle:ai-public')
    ->name('ai.book-assistant.messages');

Route::middleware('auth')->group(function () {
    Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'create'])->name('twofactor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'store'])->name('twofactor.verify');
    Route::post('/two-factor/resend', [TwoFactorChallengeController::class, 'resend'])->name('twofactor.resend');

    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->middleware('verified')->name('twofactor.enable');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('twofactor.disable');
});

Route::get('/dashboard', function () {
    $user = request()->user();

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('customer.dashboard');
})->middleware(['auth', 'twofactor'])->name('dashboard');

Route::middleware(['auth', 'twofactor'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::get('/exports/{exportLog}/download', [DataManagementController::class, 'downloadExport'])->name('exports.download');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'twofactor', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', AdminCategoryController::class)->except('show');
        Route::resource('books', AdminBookController::class)->except('show');

        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');

        Route::get('/data-management', [DataManagementController::class, 'index'])->name('data.index');
        Route::get('/data-management/template/books', [DataManagementController::class, 'bookTemplate'])->name('data.template.books');
        Route::get('/data-management/template/users', [DataManagementController::class, 'userTemplate'])->name('data.template.users');
        Route::post('/data-management/imports/books', [DataManagementController::class, 'importBooks'])->name('data.imports.books');
        Route::post('/data-management/imports/users', [DataManagementController::class, 'importUsers'])->name('data.imports.users');
        Route::post('/data-management/exports/books', [DataManagementController::class, 'exportBooks'])->name('data.exports.books');
        Route::post('/data-management/exports/orders', [DataManagementController::class, 'exportOrders'])->name('data.exports.orders');
        Route::post('/data-management/exports/users', [DataManagementController::class, 'exportUsers'])->name('data.exports.users');
        Route::get('/data-management/imports/{importLog}/failures', [DataManagementController::class, 'downloadImportFailures'])->name('data.imports.failures');
        Route::post('/data-management/backups/run', [DataManagementController::class, 'runBackup'])->name('data.backups.run');

        Route::get('/audits', [AuditLogController::class, 'index'])->name('audits.index');
        Route::post('/audits/export', [AuditLogController::class, 'export'])->name('audits.export');

        Route::get('/ai-monitoring', [AiMonitoringController::class, 'labEightIndex'])->name('ai-monitoring.index');
        Route::get('/ai', [AiMonitoringController::class, 'index'])->name('ai.index');
        Route::get('/ai/{aiInteraction}', [AiMonitoringController::class, 'show'])->name('ai.show');
    });

Route::middleware(['auth', 'twofactor', 'customer'])->group(function () {
    Route::get('/customer/dashboard', [CustomerDashboardController::class, 'index'])->name('customer.dashboard');
    Route::get('/customer/exports/personal-data', [DataExportController::class, 'personalData'])->name('customer.exports.personal');
    Route::post('/customer/exports/orders', [DataExportController::class, 'orders'])->name('customer.exports.orders');
    Route::get('/customer/exports/reading-history', [DataExportController::class, 'readingHistory'])->name('customer.exports.reading');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/books/{book:slug}', [CartController::class, 'store'])->name('cart.store');
    Route::post('/buy-now/books/{book:slug}', [CartController::class, 'buyNow'])->name('cart.buy_now');
    Route::patch('/cart-items/{cartItem}', [CartController::class, 'update'])->name('cart-items.update');
    Route::delete('/cart-items/{cartItem}', [CartController::class, 'destroy'])->name('cart-items.destroy');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');

    Route::middleware('verified')->group(function () {
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::get('/order-success/{order}', [CheckoutController::class, 'success'])->name('orders.success');

        Route::post('/books/{book:slug}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    });
});

require __DIR__.'/auth.php';

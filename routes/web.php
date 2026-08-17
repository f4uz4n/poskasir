<?php

use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Developer\DashboardController as DeveloperDashboardController;
use App\Http\Controllers\Developer\PaymentController as DeveloperPaymentController;
use App\Http\Controllers\Developer\PlanController as DeveloperPlanController;
use App\Http\Controllers\Developer\SettingController as DeveloperSettingController;
use App\Http\Controllers\ExpiryMonitorController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\PayableController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\PriceTagController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\RemoteMonitorController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isDeveloper()
        ? redirect()->route('developer.dashboard')
        : redirect()->route('dashboard');
});

Route::get('/monitor/{token}', [RemoteMonitorController::class, 'show'])->name('remote.monitor');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/subscription/payment/{payment}/proof-image', [SubscriptionController::class, 'showProof'])->name('subscription.proof.image');

    Route::middleware('developer')->prefix('developer')->name('developer.')->group(function () {
        Route::get('/', [DeveloperDashboardController::class, 'index'])->name('dashboard');
        Route::post('/users/{user}/toggle', [DeveloperDashboardController::class, 'toggleUser'])->name('users.toggle');
        Route::post('/users/{user}/plan', [DeveloperDashboardController::class, 'assignPlan'])->name('users.plan');
        Route::get('/plans', [DeveloperPlanController::class, 'index'])->name('plans.index');
        Route::post('/plans', [DeveloperPlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{plan}', [DeveloperPlanController::class, 'update'])->name('plans.update');
        Route::get('/payments', [DeveloperPaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{payment}/approve', [DeveloperPaymentController::class, 'approve'])->name('payments.approve');
        Route::post('/payments/{payment}/reject', [DeveloperPaymentController::class, 'reject'])->name('payments.reject');
        Route::get('/settings', [DeveloperSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [DeveloperSettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('owner')->group(function () {
        Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('/subscription', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
        Route::get('/subscription/payment/{payment}', [SubscriptionController::class, 'payment'])->name('subscription.payment');
        Route::post('/subscription/payment/{payment}/proof', [SubscriptionController::class, 'uploadProof'])->name('subscription.proof');
        Route::post('/subscription/payment/{payment}/verify', [SubscriptionController::class, 'verifyPayment'])->name('subscription.verify');
        Route::get('/subscription/payment/{payment}/status', [SubscriptionController::class, 'paymentStatus'])->name('subscription.payment.status');
        Route::post('/subscription/payment/{payment}/demo', [SubscriptionController::class, 'confirmDemo'])->name('subscription.demo');

        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup/export', [BackupController::class, 'export'])->name('backup.export');
        Route::post('/backup/restore', [BackupController::class, 'restore'])->name('backup.restore');
        Route::get('/backup/download/{filename}', [BackupController::class, 'download'])->name('backup.download');

        Route::post('/remote/enable', [RemoteMonitorController::class, 'enable'])->name('remote.enable');
        Route::post('/remote/regenerate', [RemoteMonitorController::class, 'regenerate'])->name('remote.regenerate');
        Route::post('/remote/disable', [RemoteMonitorController::class, 'disable'])->name('remote.disable');
    });

    Route::middleware('subscribed')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/printer/devices', [PrinterController::class, 'devices'])->name('printer.devices');
        Route::post('/printer/raw', [PrinterController::class, 'printRaw'])->name('printer.raw');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/toggle-lock', [ProductController::class, 'toggleLock'])->name('products.toggle-lock');

        Route::get('/price-tags', [PriceTagController::class, 'index'])->name('price-tags.index');
        Route::post('/price-tags/print', [PriceTagController::class, 'print'])->name('price-tags.print');

        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('/transactions/recent', [TransactionController::class, 'recent'])->name('transactions.recent');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::post('/transactions/{transaction}/void', [TransactionController::class, 'void'])->name('transactions.void');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/stock', [StockReportController::class, 'index'])->name('reports.stock');
        Route::get('/reports/profit-loss', [ProfitLossController::class, 'index'])->name('reports.profit-loss');
        Route::get('/expiry', [ExpiryMonitorController::class, 'index'])->name('expiry.index');

        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');

        Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
        Route::post('/receivables', [ReceivableController::class, 'store'])->name('receivables.store');
        Route::post('/receivables/{receivable}/pay', [ReceivableController::class, 'pay'])->name('receivables.pay');

        Route::get('/payables', [PayableController::class, 'index'])->name('payables.index');
        Route::post('/payables', [PayableController::class, 'store'])->name('payables.store');
        Route::post('/payables/{payable}/pay', [PayableController::class, 'pay'])->name('payables.pay');

        Route::get('/stock-opname', [StockOpnameController::class, 'index'])->name('stock-opname.index');
        Route::get('/stock-opname/create', [StockOpnameController::class, 'create'])->name('stock-opname.create');
        Route::post('/stock-opname', [StockOpnameController::class, 'store'])->name('stock-opname.store');
        Route::get('/stock-opname/{stockOpname}', [StockOpnameController::class, 'show'])->name('stock-opname.show');
        Route::post('/stock-opname/{stockOpname}/complete', [StockOpnameController::class, 'complete'])->name('stock-opname.complete');
        Route::delete('/stock-opname/{stockOpname}', [StockOpnameController::class, 'destroy'])->name('stock-opname.destroy');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/offline/enable', [SettingController::class, 'enableOffline'])->name('settings.offline.enable');
        Route::post('/settings/offline/disable', [SettingController::class, 'disableOffline'])->name('settings.offline.disable');
        Route::post('/settings/wipe', [SettingController::class, 'wipe'])->middleware('owner')->name('settings.wipe');

        Route::middleware(['owner', 'feature:api_sync'])->group(function () {
            Route::post('/settings/api-token/generate', [ApiTokenController::class, 'generate'])->name('settings.api-token.generate');
            Route::delete('/settings/api-token/revoke', [ApiTokenController::class, 'revoke'])->name('settings.api-token.revoke');
        });

        Route::middleware(['owner', 'feature:multi_kasir'])->group(function () {
            Route::get('/kasir', [KasirController::class, 'index'])->name('kasir.index');
            Route::post('/kasir', [KasirController::class, 'store'])->name('kasir.store');
            Route::put('/kasir/{kasir}', [KasirController::class, 'update'])->name('kasir.update');
            Route::delete('/kasir/{kasir}', [KasirController::class, 'destroy'])->name('kasir.destroy');
        });

        Route::prefix('api/sync')->name('api.sync.')->group(function () {
            Route::get('/pull', [SyncController::class, 'pull'])->name('pull');
            Route::post('/push', [SyncController::class, 'push'])->name('push');
            Route::get('/products', [SyncController::class, 'products'])->name('products');
        });
    });
});

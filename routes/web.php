<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\NotifierController;
use App\Http\Controllers\Admin\BillAdminController;
use App\Http\Controllers\Admin\PlanAdminController;
use App\Http\Controllers\Admin\PreferenceController;
use App\Http\Controllers\Admin\WebhookLogController;
// use App\Http\Controllers\Admin\ChapaWebhookController;
use App\Http\Controllers\Admin\AIEngineAdminController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\StripeWebhookController;
use App\Http\Controllers\Admin\DashboardAdminController;
use App\Http\Controllers\Admin\TokenUsageAdminController;
// use App\Http\Controllers\Admin\FlutterwaveWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\Admin\SubscriptionAdminController;
use App\Http\Controllers\Admin\PaymentAdminController;


// 🌐 Public landing page
Route::get('/', function () {
    return view('welcome');
});

// 👤 User dashboard (Breeze)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// 👤 Profile routes (Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 🛠️ Admin Panel Routes (Laravel 12 class-based middleware)
Route::middleware(['auth', AdminMiddleware::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard Home
    Route::get('/dashboard', [DashboardAdminController::class, 'index'])->name('dashboard');

    // AI Engines CRUD
    Route::get('ai-engines/export', [AIEngineAdminController::class, 'export'])->name('ai-engines.export');
    Route::get('ai-engines/{ai_engine}/test', [AIEngineAdminController::class, 'test'])->name('ai-engines.test');
    Route::resource('ai-engines', AIEngineAdminController::class);

    // Plans CRUD
    Route::get('plans/export', [PlanAdminController::class, 'export'])->name('plans.export');
    Route::resource('plans', PlanAdminController::class);

    // Subscriptions CRUD
    Route::get('subscriptions/export', [SubscriptionAdminController::class, 'export'])->name('subscriptions.export');
    Route::resource('subscriptions', SubscriptionAdminController::class);
    Route::get('subscriptions/grace-period', [SubscriptionAdminController::class, 'gracePeriod'])->name('subscriptions.gracePeriod');
    Route::get('subscriptions/payment-failures', [SubscriptionAdminController::class, 'paymentFailures'])->name('subscriptions.paymentFailures');
    Route::get('subscriptions/expiring', [SubscriptionAdminController::class, 'expiring'])->name('subscriptions.expiring');
    Route::get('subscriptions/stripe', [SubscriptionAdminController::class, 'stripeSubscriptions'])->name('subscriptions.stripe');
    Route::post('subscriptions/{subscription}/extend-grace', [SubscriptionAdminController::class, 'extendGracePeriod'])->name('subscriptions.extendGrace');
    Route::post('subscriptions/{subscription}/process-grace', [SubscriptionAdminController::class, 'processGraceExpiration'])->name('subscriptions.processGrace');
    Route::post('subscriptions/{subscription}/trigger-renewal', [SubscriptionAdminController::class, 'triggerRenewal'])->name('subscriptions.triggerRenewal');
    Route::post('subscriptions/{subscription}/reset-failure', [SubscriptionAdminController::class, 'resetFailureCount'])->name('subscriptions.resetFailure');
    
    Route::get('usage-logs', [TokenUsageAdminController::class, 'index'])->name('usage.logs');
    Route::get('usage-logs/export', [TokenUsageAdminController::class, 'export'])->name('usage.logs.export');


    // Billing Management Routes
    Route::get('billing', [BillAdminController::class, 'index'])->name('billing.index');
    Route::get('billing/export-subscriptions', [BillAdminController::class, 'exportSubscriptions'])->name('billing.export-subscriptions');
    Route::get('billing/user/{user}', [BillAdminController::class, 'userHistory'])->name('billing.userHistory');
    Route::get('billing/adjust/{subscription}', [BillAdminController::class, 'editUsage'])->name('billing.editUsage');
    Route::post('billing/adjust/{subscription}', [BillAdminController::class, 'updateUsage'])->name('billing.updateUsage');

    // Bill Management Routes
    Route::get('bills', [BillAdminController::class, 'bills'])->name('bills.index');
    Route::get('bills/export', [BillAdminController::class, 'exportBills'])->name('bills.export');
    Route::get('bills/{bill}', [BillAdminController::class, 'showBill'])->name('bills.show');
    Route::post('bills/{bill}/mark-paid', [BillAdminController::class, 'markAsPaid'])->name('bills.markPaid');
    Route::post('bills/{bill}/cancel', [BillAdminController::class, 'cancel'])->name('bills.cancel');

    Route::get('payment-methods/export', [PaymentMethodController::class, 'export'])->name('payment-methods.export');
    Route::resource('payment-methods', PaymentMethodController::class);
    
    // System Settings Routes
    Route::get('system-settings', [\App\Http\Controllers\Admin\SystemSettingController::class, 'index'])->name('system-settings.index');
    Route::put('system-settings', [\App\Http\Controllers\Admin\SystemSettingController::class, 'update'])->name('system-settings.update');
    Route::post('system-settings/{key}/reset', [\App\Http\Controllers\Admin\SystemSettingController::class, 'reset'])->name('system-settings.reset');
    
    // Payment Management Routes
    Route::get('payments/export', [PaymentAdminController::class, 'export'])->name('payments.export');
    Route::get('payments/user/{user}', [PaymentAdminController::class, 'userPayments'])->name('payments.user');
    Route::resource('payments', PaymentAdminController::class);
    Route::post('payments/{payment}/refund', [PaymentAdminController::class, 'refund'])->name('payments.refund');
    Route::post('payments/{payment}/verify', [PaymentAdminController::class, 'verify'])->name('payments.verify');
    Route::get('payments/stats/summary', [PaymentAdminController::class, 'stats'])->name('payments.stats');
    
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class]);
    // Route::post('/webhooks/chapa', [ChapaWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class]);
    // Route::post('/webhooks/flutterwave', [FlutterwaveWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class]);

    Route::get('webhooks', [WebhookLogController::class, 'index'])->name('webhooks.index');
    Route::get('webhooks/export', [WebhookLogController::class, 'export'])->name('webhooks.export');
    Route::get('webhooks/{id}', [WebhookLogController::class, 'show'])->name('webhooks.show');

    // Visitor Management
    Route::get('visitors', [\App\Http\Controllers\Admin\VisitorAdminController::class, 'index'])->name('visitors.index');
    Route::get('visitors/export', [\App\Http\Controllers\Admin\VisitorAdminController::class, 'export'])->name('visitors.export');
    Route::get('visitors/{visitor}', [\App\Http\Controllers\Admin\VisitorAdminController::class, 'show'])->name('visitors.show');
    Route::get('visitors/analytics/data', [\App\Http\Controllers\Admin\VisitorAdminController::class, 'analytics'])->name('visitors.analytics');

    Route::get('preferences', [PreferenceController::class, 'index'])->name('preferences.index');
    Route::get('preferences/export', [PreferenceController::class, 'export'])->name('preferences.export');
    Route::put('preferences/{preference}', [PreferenceController::class, 'update'])->name('preferences.update');
    
    Route::view('notifier', 'admin.notifier.index')->name('notifier.index');

    Route::post('notifier/email', [NotifierController::class, 'sendEmail'])->name('notifier.email');
    Route::post('notifier/push', [NotifierController::class, 'sendPush'])->name('notifier.push');
    // Route::post('notifier/sms', [NotifierController::class, 'sendSms'])->name('notifier.sms'); // Uncomment when ready
    Route::post('notifier/broadcast', [NotifierController::class, 'broadcast'])->name('notifier.broadcast');

    // Admin User Management
    Route::get('admins/export', [\App\Http\Controllers\Admin\AdminUserController::class, 'export'])->name('admins.export');
    Route::resource('admins', \App\Http\Controllers\Admin\AdminUserController::class);
    Route::post('admins/{admin}/activate', [\App\Http\Controllers\Admin\AdminUserController::class, 'activate'])->name('admins.activate');
    Route::post('admins/{admin}/deactivate', [\App\Http\Controllers\Admin\AdminUserController::class, 'deactivate'])->name('admins.deactivate');

    // Audit Logs
    Route::get('audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/export', [\App\Http\Controllers\Admin\AuditLogController::class, 'export'])->name('audit-logs.export');
    Route::get('audit-logs/{log}', [\App\Http\Controllers\Admin\AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::get('audit-logs/admin/{admin}', [\App\Http\Controllers\Admin\AuditLogController::class, 'forAdmin'])->name('audit-logs.admin');

});




// 🔐 Auth routes (login, register, etc.)
require __DIR__.'/auth.php';
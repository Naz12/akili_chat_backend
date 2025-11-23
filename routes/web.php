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
    Route::resource('ai-engines', AIEngineAdminController::class);

    // Plans CRUD
    Route::resource('plans', PlanAdminController::class);

    // Subscriptions CRUD
    Route::resource('subscriptions', SubscriptionAdminController::class);
    
    Route::get('usage-logs', [TokenUsageAdminController::class, 'index'])->name('usage.logs');

    // ✅ Correct
    Route::get('ai-engines/{ai_engine}/test', [AIEngineAdminController::class, 'test'])->name('ai-engines.test');


    // Billing Management Routes
    Route::get('billing', [BillAdminController::class, 'index'])->name('billing.index');
    Route::get('billing/user/{user}', [BillAdminController::class, 'userHistory'])->name('billing.userHistory');
    Route::get('billing/adjust/{subscription}', [BillAdminController::class, 'editUsage'])->name('billing.editUsage');
    Route::post('billing/adjust/{subscription}', [BillAdminController::class, 'updateUsage'])->name('billing.updateUsage');

    Route::resource('payment-methods', PaymentMethodController::class);
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class]);
    // Route::post('/webhooks/chapa', [ChapaWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class]);
    // Route::post('/webhooks/flutterwave', [FlutterwaveWebhookController::class, 'handle'])->withoutMiddleware([VerifyCsrfToken::class]);

    Route::get('webhooks', [WebhookLogController::class, 'index'])->name('webhooks.index');
    Route::get('webhooks/{id}', [WebhookLogController::class, 'show'])->name('webhooks.show');

    Route::put('preferences/{preference}', [PreferenceController::class, 'update'])->name('preferences.update');
    Route::get('preferences', [PreferenceController::class, 'index'])->name('preferences.index');
    Route::put('preferences/{preference}', [PreferenceController::class, 'update'])->name('preferences.update');
    
    Route::view('notifier', 'admin.notifier.index')->name('notifier.index');

    Route::post('notifier/email', [NotifierController::class, 'sendEmail'])->name('notifier.email');
    Route::post('notifier/push', [NotifierController::class, 'sendPush'])->name('notifier.push');
    // Route::post('notifier/sms', [NotifierController::class, 'sendSms'])->name('notifier.sms'); // Uncomment when ready
    Route::post('notifier/broadcast', [NotifierController::class, 'broadcast'])->name('notifier.broadcast');

});




// 🔐 Auth routes (login, register, etc.)
require __DIR__.'/auth.php';
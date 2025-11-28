<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\WorkflowInsightsController;
use App\Http\Controllers\Api\BillApiController;
use App\Http\Controllers\Api\PlanApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\AIChatApiController;
use App\Http\Controllers\Api\AppVersionApiController;
use App\Http\Controllers\Api\TokenUsageApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\SubscriptionApiController;
use App\Http\Controllers\Api\PaymentMethodApiController;
use App\Http\Controllers\Api\AiChatHistoryApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PaymentWebhookController;


Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthApiController::class, 'register']);
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::post('/refresh-token', [AuthApiController::class, 'refreshToken']);
    Route::post('/login/google', [AuthApiController::class, 'loginWithGoogle']);
    Route::post('/send-reset-code', [AuthApiController::class, 'sendResetCode'])->middleware('throttle:3,1');
    Route::post('/reset-password-code', [AuthApiController::class, 'resetPasswordWithCode']);
    
    

    foreach (['local', 'intl'] as $prefix) {
        Route::prefix($prefix)->group(function () use ($prefix) {
            Route::get('/check-version', [AppVersionApiController::class, 'check']);

            Route::middleware('auth:api')->group(function () use ($prefix) {
                Route::get('/user', [UserApiController::class, 'profile']);
                Route::post('/logout', [UserApiController::class, 'logout']);
                Route::post('/fcm-token', [UserApiController::class, 'storeFcmToken']);

                Route::middleware(['ensure.region.match'])->group(function () use ($prefix) {
                    // 🗂️ Plans & Subscriptions
                    Route::get('/plans', [PlanApiController::class, 'index']);
                    Route::post('/subscribe', [SubscriptionApiController::class, 'store']);
                    Route::get('/subscription', [SubscriptionApiController::class, 'current']);
                    Route::get('/user/has-subscription', [SubscriptionApiController::class, 'hasSubscription']);

                    // 💬 AI Chat + Chat History
                    Route::prefix('chat')->middleware(['throttle:60,1'])->group(function () {
                        // Only apply quota check to chat creation
                        Route::post('/', [AIChatApiController::class, 'handleChat'])
                            ->middleware('quota.check'); // POST /chat

                        // Chat sessions history (no quota check needed for viewing history)
                        Route::get('/sessions', [AiChatHistoryApiController::class, 'sessions']);
                        Route::get('/messages/{sessionId}', [AiChatHistoryApiController::class, 'messages']);
                        Route::put('/sessions/{sessionId}', [AiChatHistoryApiController::class, 'rename']);
                        Route::delete('/sessions/{sessionId}', [AiChatHistoryApiController::class, 'destroy']);
                        
                        Route::post('/upload', [AIChatApiController::class, 'uploadAttachment'])
                            ->middleware('quota.check');
                    });

                    // 📊 Token Usage
                    Route::get('/token-usage', [TokenUsageApiController::class, 'index']);
                    Route::get('/token-usage/stats', [TokenUsageApiController::class, 'stats']);

                    // 🔔 Notifications
                    Route::get('/notifications', [NotificationApiController::class, 'index']);
                    Route::post('/notifications/read', [NotificationApiController::class, 'markAllRead']);

                    // 📄 Billing APIs
                    Route::get('/billing/current', [BillApiController::class, 'current']);
                    Route::get('/billing/history', [BillApiController::class, 'history']);
                    Route::get('/billing/usage', [BillApiController::class, 'usage']);
                    Route::post('/billing/toggle-renew', [BillApiController::class, 'toggleAutoRenew']);

                    // 💰 Region-based Subscription
                    Route::post('/regional-subscribe', [PaymentMethodApiController::class, 'subscribe']);
                    Route::post('/payment/telebirr/callback', [SubscriptionApiController::class, 'handleTelebirrCallback'])
                        ->name("api.payment.telebirr.callback.{$prefix}");
                    
                    // 💳 Payment Endpoints
                    Route::post('/payments/create', [PaymentApiController::class, 'create']);
                    Route::get('/payments/status', [PaymentApiController::class, 'status']);
                    Route::post('/payments/verify', [PaymentApiController::class, 'verify']);
                    Route::get('/payments/history', [PaymentApiController::class, 'history']);
                    
                    // 🧠 Workflow Insights & Optimization
                    Route::prefix('insights')->group(function () {
                        Route::get('/summary', [WorkflowInsightsController::class, 'summary']);
                        Route::get('/recommendations', [WorkflowInsightsController::class, 'recommendations']);
                    });
                });
            });
        });
    }
    
    // Webhook endpoints (public, signature verified, CSRF exempt)
    Route::post('/payments/webhook/stripe', [PaymentWebhookController::class, 'handleStripe'])
        ->name('api.payments.webhook.stripe')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::match(['GET', 'POST'], '/payments/webhook/chapa', [PaymentWebhookController::class, 'handleChapa'])
        ->name('api.payments.webhook.chapa')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    
    // Redirect endpoint for Chapa return_url (processes and redirects to frontend)
    Route::get('/payments/webhook/chapa/redirect', [PaymentWebhookController::class, 'handleChapa'])
        ->name('api.payments.webhook.chapa.redirect')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
});
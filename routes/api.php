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
use App\Http\Controllers\Api\ChatSessionShareController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\VisitorApiController;
use App\Http\Controllers\Api\WebPushApiController;
use App\Http\Controllers\Api\WebSocketApiController;
use App\Http\Controllers\Api\PresentationController;
use App\Http\Controllers\Api\DiagramController;
use App\Http\Controllers\Api\DocConverterController;


Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthApiController::class, 'register']);
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::post('/refresh-token', [AuthApiController::class, 'refreshToken']);
    Route::post('/login/google', [AuthApiController::class, 'loginWithGoogle']);
    // Password Reset Endpoints (increased rate limit for testing)
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:20,1');
    Route::post('/send-reset-code', [AuthApiController::class, 'sendResetCode'])->middleware('throttle:20,1'); // Legacy endpoint
    Route::post('/reset-password', [AuthApiController::class, 'resetPasswordWithCode'])->middleware('throttle:20,1');
    Route::post('/reset-password-code', [AuthApiController::class, 'resetPasswordWithCode'])->middleware('throttle:20,1'); // Legacy endpoint
    
    // 📊 Visitor Tracking (Public - no auth required)
    Route::post('/visitors/track', [VisitorApiController::class, 'track'])->middleware('throttle:100,1');
    Route::post('/visitors/activity', [VisitorApiController::class, 'updateActivity'])->middleware('throttle:60,1');
    
    

    foreach (['local', 'intl'] as $prefix) {
        Route::prefix($prefix)->group(function () use ($prefix) {
            // Password Reset Endpoints (available in all regions, increased rate limit for testing)
            Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:20,1');
            Route::post('/send-reset-code', [AuthApiController::class, 'sendResetCode'])->middleware('throttle:20,1'); // Legacy endpoint
            Route::post('/reset-password', [AuthApiController::class, 'resetPasswordWithCode'])->middleware('throttle:20,1');
            Route::post('/reset-password-code', [AuthApiController::class, 'resetPasswordWithCode'])->middleware('throttle:20,1'); // Legacy endpoint
            
            Route::get('/check-version', [AppVersionApiController::class, 'check']);

            // 💬 AI Chat + Chat History (accessible to guests and authenticated users)
            Route::prefix('chat')->middleware(['throttle:60,1'])->group(function () {
                // Only apply quota check to chat creation (allows guests)
                Route::post('/', [AIChatApiController::class, 'handleChat'])
                    ->middleware('quota.check'); // POST /chat

                // Chat sessions history (no quota check needed for viewing history)
                Route::get('/sessions', [AiChatHistoryApiController::class, 'sessions']);
                Route::get('/messages/{sessionId}', [AiChatHistoryApiController::class, 'messages']);
                Route::put('/sessions/{sessionId}', [AiChatHistoryApiController::class, 'rename']);
                Route::delete('/sessions/{sessionId}', [AiChatHistoryApiController::class, 'destroy']);
                
                Route::post('/upload', [AIChatApiController::class, 'uploadAttachment'])
                    ->middleware('quota.check');
                Route::patch('/sessions/{sessionId}/messages/{messageId}', [AiChatHistoryApiController::class, 'updateMessage']);
            });

            // Tools: PPT, diagram, doc-converter (throttle only; quota checked in controllers)
            Route::middleware(['throttle:60,1'])->group(function () {
                Route::post('presentations/generate-outline', [PresentationController::class, 'generateOutline']);
                Route::post('presentations/generate-content', [PresentationController::class, 'generateContent']);
                Route::post('presentations/export', [PresentationController::class, 'export']);
                Route::get('presentations/templates', [PresentationController::class, 'templates']);
                Route::get('presentations/status', [PresentationController::class, 'status']);
                Route::get('presentations/result', [PresentationController::class, 'result']);
                Route::get('presentations/files/{fileId}/download', [PresentationController::class, 'download']);
                Route::post('diagram/generate', [DiagramController::class, 'generate']);
                Route::get('diagram/status', [DiagramController::class, 'status']);
                Route::get('diagram/result', [DiagramController::class, 'result']);
                Route::get('diagram/files/{fileId}/download', [DiagramController::class, 'download']);
                Route::post('doc-converter/convert', [DocConverterController::class, 'convert']);
                Route::get('doc-converter/status', [DocConverterController::class, 'status']);
                Route::get('doc-converter/result', [DocConverterController::class, 'result']);
            });

            Route::middleware('auth:api')->group(function () use ($prefix) {
                Route::get('/user', [UserApiController::class, 'profile']);
                Route::post('/logout', [UserApiController::class, 'logout']);
                Route::post('/fcm-token', [UserApiController::class, 'storeFcmToken']);
                
                // 🔔 Web Push API
                Route::get('/webpush/vapid-key', [WebPushApiController::class, 'vapidPublicKey']);
                Route::post('/webpush/subscribe', [WebPushApiController::class, 'subscribe']);
                Route::post('/webpush/unsubscribe', [WebPushApiController::class, 'unsubscribe']);
                Route::get('/webpush/subscriptions', [WebPushApiController::class, 'subscriptions']);
                
                // 🔌 WebSocket API
                Route::get('/websocket/config', [WebSocketApiController::class, 'config']);
                Route::post('/websocket/authenticate', [WebSocketApiController::class, 'authenticate']);

                Route::middleware(['ensure.region.match'])->group(function () use ($prefix) {
                    // 🗂️ Plans & Subscriptions
                    Route::get('/plans', [PlanApiController::class, 'index']);
                    Route::post('/subscribe', [SubscriptionApiController::class, 'store']);
                    Route::get('/subscription', [SubscriptionApiController::class, 'current']);
                    Route::get('/user/has-subscription', [SubscriptionApiController::class, 'hasSubscription']);

                    // 📤 Chat Session Sharing (requires authentication)
                    Route::prefix('chat')->group(function () {
                        Route::post('/sessions/{sessionId}/share', [ChatSessionShareController::class, 'share']);
                        Route::get('/shares/incoming', [ChatSessionShareController::class, 'incoming']);
                        Route::get('/shares/outgoing', [ChatSessionShareController::class, 'outgoing']);
                        Route::get('/shares/{shareId}', [ChatSessionShareController::class, 'show']);
                        Route::post('/shares/{shareId}/accept', [ChatSessionShareController::class, 'accept']);
                        Route::post('/shares/{shareId}/decline', [ChatSessionShareController::class, 'decline']);
                    });

                    // 📊 Token Usage
                    Route::get('/token-usage', [TokenUsageApiController::class, 'index']);
                    Route::get('/token-usage/stats', [TokenUsageApiController::class, 'stats']);
                    Route::get('/token-usage/daily', [TokenUsageApiController::class, 'daily']);
                    Route::get('/token-usage/weekly', [TokenUsageApiController::class, 'weekly']);
                    Route::get('/token-usage/monthly', [TokenUsageApiController::class, 'monthly']);
                    Route::get('/token-usage/analytics', [TokenUsageApiController::class, 'analytics']);
                    Route::get('/token-usage/quota-status', [TokenUsageApiController::class, 'quotaStatus']);
                    Route::get('/token-usage/export', [TokenUsageApiController::class, 'export']);

                    // 🔔 Notifications
                    Route::get('/notifications', [NotificationApiController::class, 'index']);
                    Route::post('/notifications/{notificationId}/read', [NotificationApiController::class, 'markRead']);
                    Route::post('/notifications/read', [NotificationApiController::class, 'markAllRead']);

                    // 📄 Billing APIs
                    Route::get('/billing/current', [BillApiController::class, 'current']);
                    Route::get('/billing/history', [BillApiController::class, 'history']);
                    Route::get('/billing/usage', [BillApiController::class, 'usage']);
                    Route::post('/billing/toggle-renew', [BillApiController::class, 'toggleAutoRenew']);
                    Route::get('/billing/invoices', [BillApiController::class, 'invoices']);
                    Route::get('/billing/invoices/{id}/download', [BillApiController::class, 'downloadInvoice']);
                    Route::get('/billing/invoices/{id}', [BillApiController::class, 'invoice']);
                    Route::get('/billing/upcoming-charges', [BillApiController::class, 'upcomingCharges']);
                    Route::get('/billing/payment-methods', [BillApiController::class, 'paymentMethods']);
                    Route::post('/billing/payment-methods', [BillApiController::class, 'addPaymentMethod']);
                    Route::delete('/billing/payment-methods/{id}', [BillApiController::class, 'removePaymentMethod']);
                    Route::put('/billing/payment-methods/{id}/default', [BillApiController::class, 'setDefaultPaymentMethod']);
                    Route::get('/billing/address', [BillApiController::class, 'billingAddress']);
                    Route::put('/billing/address', [BillApiController::class, 'billingAddress']);
                    Route::get('/billing/tax-information', [BillApiController::class, 'taxInformation']);

                    // 💰 Bill Management (Pending Bills, Pay Bills)
                    Route::get('/bills', [BillApiController::class, 'listBills']);
                    Route::get('/bills/pending', [BillApiController::class, 'pendingBills']);
                    Route::get('/bills/{id}', [BillApiController::class, 'getBill']);
                    Route::post('/bills/{id}/pay', [BillApiController::class, 'payBill']);

                    // 💰 Region-based Subscription
                    Route::post('/regional-subscribe', [PaymentMethodApiController::class, 'subscribe']);
                    
                    // 💰 Available Payment Methods (for frontend selection)
                    Route::get('/payment-methods/available', [PaymentMethodApiController::class, 'index']);
                    Route::post('/payment/telebirr/callback', [SubscriptionApiController::class, 'handleTelebirrCallback'])
                        ->name("api.payment.telebirr.callback.{$prefix}");
                    
                    // 💳 Payment Endpoints
                    Route::post('/payments/create', [PaymentApiController::class, 'create']);
                    Route::get('/payments/status', [PaymentApiController::class, 'status']);
                    Route::post('/payments/verify', [PaymentApiController::class, 'verify']);
                    Route::get('/payments/history', [PaymentApiController::class, 'history']);
                    Route::get('/payments/{id}', [PaymentApiController::class, 'show']);
                    Route::post('/payments/{id}/retry', [PaymentApiController::class, 'retry']);
                    Route::post('/payments/{id}/cancel', [PaymentApiController::class, 'cancel']);
                    Route::post('/payments/{id}/refund', [PaymentApiController::class, 'refund']);
                    Route::get('/payments/{id}/refund-status', [PaymentApiController::class, 'refundStatus']);
                    Route::get('/payments/{id}/receipt', [PaymentApiController::class, 'downloadReceipt']);
                    Route::get('/payments/summary', [PaymentApiController::class, 'summary']);
                    Route::get('/payments/upcoming', [PaymentApiController::class, 'upcoming']);
                    
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
<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CreditSettingController;
use App\Http\Controllers\Api\CreditSubmissionController;
use App\Http\Controllers\Api\FontController;
use App\Http\Controllers\Api\LandingController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaperController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PdfExportController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectAiResultController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SharedDocumentController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['message' => 'pong']));

// ---- Asisten chat landing (publik, hanya tanya-jawab) ----
Route::post('/chat', [ChatController::class, 'store']);

// ---- Landing page (harga langganan + mesin AI) & review publik ----
Route::get('/landing-settings', [LandingController::class, 'index']);
Route::get('/reviews/published', [ReviewController::class, 'published']);

// ---- Auth (Sanctum) ----
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

    // `/me` dibiarkan publik agar saat belum login tidak memicu 401 di console;
    // endpoint mengembalikan `user: null` bila tidak ada sesi aktif.
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/send-verification', [AuthController::class, 'sendVerificationNotification']);
    });
});

// ---- Panel Admin (super-admin) ----
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->middleware('permission:analytics.view');

    Route::get('/users', [AdminController::class, 'users'])->middleware('permission:users.view');
    Route::patch('/users/{id}', [AdminController::class, 'updateUser'])->middleware('permission:users.manage');
    Route::get('/roles', [AdminController::class, 'roles'])->middleware('permission:roles.manage');

    Route::get('/credit-submissions', [AdminController::class, 'creditSubmissions'])->middleware('permission:submissions.review');
    Route::post('/credit-submissions/{id}/review', [AdminController::class, 'reviewCreditSubmission'])->middleware('permission:submissions.review');

    Route::get('/projects', [AdminController::class, 'projects'])->middleware('permission:projects.view_all');
    Route::get('/ai-results', [AdminController::class, 'aiResults'])->middleware('permission:analytics.view');
    Route::get('/credit-transactions', [AdminController::class, 'creditTransactions'])->middleware('permission:credits.view');
    Route::get('/shared-documents', [AdminController::class, 'sharedDocuments'])->middleware('permission:projects.view_all');
    Route::get('/exports', [AdminController::class, 'exports'])->middleware('permission:audit.view');

    Route::get('/payments', [AdminController::class, 'payments'])->middleware('permission:payments.view');
    Route::get('/topup-orders', [AdminController::class, 'topupOrders'])->middleware('permission:payments.view');

    Route::get('/affiliates', [AdminController::class, 'affiliates'])->middleware('permission:affiliates.view');
    Route::post('/affiliates/commissions/{id}/review', [AdminController::class, 'reviewCommission'])->middleware('permission:affiliates.approve');

    Route::get('/referrals', [AdminController::class, 'referrals'])->middleware('permission:affiliates.view');
    Route::post('/referrals/{id}/review', [AdminController::class, 'reviewReferral'])->middleware('permission:affiliates.approve');

    Route::get('/tickets', [AdminController::class, 'tickets'])->middleware('permission:tickets.manage');
    Route::patch('/tickets/{id}', [AdminController::class, 'updateTicket'])->middleware('permission:tickets.manage');

    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->middleware('permission:audit.view');

    Route::get('/credit-settings', [CreditSettingController::class, 'settings'])->middleware('permission:credits.adjust');
    Route::put('/credit-settings', [CreditSettingController::class, 'update'])->middleware('permission:credits.adjust');

    Route::get('/subscription-settings', [SubscriptionController::class, 'settings'])->middleware('permission:subscriptions.manage');
    Route::put('/subscription-settings', [SubscriptionController::class, 'update'])->middleware('permission:subscriptions.manage');

    Route::get('/coupons', [CouponController::class, 'index'])->middleware('permission:coupons.manage');
    Route::post('/coupons', [CouponController::class, 'store'])->middleware('permission:coupons.manage');
    Route::put('/coupons/{id}', [CouponController::class, 'update'])->middleware('permission:coupons.manage');
    Route::delete('/coupons/{id}', [CouponController::class, 'destroy'])->middleware('permission:coupons.manage');

    Route::post('/email-blast', [NotificationController::class, 'emailBlast'])->middleware('permission:notifications.manage');
    Route::get('/email-broadcasts', [NotificationController::class, 'emailBroadcasts'])->middleware('permission:notifications.manage');
    Route::get('/broadcast-recipients', [NotificationController::class, 'broadcastRecipients'])->middleware('permission:notifications.manage');
    Route::post('/blast-images', [NotificationController::class, 'uploadBlastImage'])->middleware('permission:notifications.manage');

    Route::get('/reviews', [AdminController::class, 'reviews'])->middleware('permission:submissions.review');
    Route::post('/reviews/{review}/moderate', [AdminController::class, 'moderateReview'])->middleware('permission:submissions.review');

    Route::get('/ai-settings', [AdminController::class, 'aiEngines'])->middleware('permission:subscriptions.manage');
    Route::put('/ai-settings', [AdminController::class, 'updateAiEngines'])->middleware('permission:subscriptions.manage');
});

// ---- Review (auth) ----
Route::middleware('auth:sanctum')->prefix('reviews')->group(function () {
    Route::post('/', [ReviewController::class, 'store']);
    Route::get('/mine', [ReviewController::class, 'mine']);
});

// ---- Tarif kredit (dibaca semua halaman untuk menampilkan biaya fitur) ----
Route::middleware('auth:sanctum')->get('/credit-pricing', [CreditSettingController::class, 'pricing']);

// ---- Pengajuan koin (user) ----
Route::middleware('auth:sanctum')->prefix('credit-submissions')->group(function () {
    Route::get('/', [CreditSubmissionController::class, 'index']);
    Route::post('/', [CreditSubmissionController::class, 'store']);
});

// ---- Paper / Journal (proxy Crossref) ----
Route::middleware('auth:sanctum')->prefix('papers')->group(function () {
    Route::get('/search', [PaperController::class, 'search'])->middleware('throttle:papers');
});

// ---- Wallet / Kredit ----
Route::middleware('auth:sanctum')->prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'show']);
    Route::get('/transactions', [WalletController::class, 'transactions']);
    Route::post('/topup', [WalletController::class, 'topup']);
    Route::post('/spend', [WalletController::class, 'spend']);
});

// ---- Pembayaran (provider: SumoPod QRIS) ----
Route::middleware('auth:sanctum')->prefix('payments')->group(function () {
    Route::get('/meta', [PaymentController::class, 'meta']);
    Route::get('/{uuid}', [PaymentController::class, 'show']);
});
Route::post('/payments/webhook/{provider}', [PaymentController::class, 'webhook']);

// ---- Langganan bulanan ----
Route::middleware('auth:sanctum')->prefix('subscription')->group(function () {
    Route::get('/', [SubscriptionController::class, 'show']);
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
});

// ---- Promo / Kupon ----
Route::middleware('auth:sanctum')->prefix('coupons')->group(function () {
    Route::get('/validate', [CouponController::class, 'validate']);
});

// ---- Template kustom (buatan user) ----
Route::middleware('auth:sanctum')->prefix('templates')->group(function () {
    Route::get('/', [TemplateController::class, 'index']);
    Route::get('/public', [TemplateController::class, 'publicIndex']);
    Route::post('/', [TemplateController::class, 'store']);
    Route::post('/{uuid}/use', [TemplateController::class, 'use']);
    Route::delete('/{uuid}', [TemplateController::class, 'destroy']);
});

// ---- Affiliate / Referral ----
Route::middleware('auth:sanctum')->prefix('affiliate')->group(function () {
    Route::get('/', [AffiliateController::class, 'show']);
    Route::post('/code', [AffiliateController::class, 'updateCode']);
});

// ---- Tulisin Workspace (parsing PDF + simpan file) ----
Route::middleware('auth:sanctum')->prefix('workspace')->group(function () {
    Route::post('/parse', [WorkspaceController::class, 'parse']);
    Route::post('/upload', [WorkspaceController::class, 'upload']);
    Route::get('/files/{id}', [WorkspaceController::class, 'show']);
    Route::delete('/files/{id}', [WorkspaceController::class, 'destroy']);
});

// ---- AI (proxy DeepSeek untuk agent canvas/copilot/turnitin/plagiarism) ----
Route::middleware('auth:sanctum')->prefix('ai')->group(function () {
    Route::post('/generate', [AiController::class, 'generate']);
    Route::get('/results', [ProjectAiResultController::class, 'mine']);
});

// ---- Shared document (bagikan dokumen agar bisa dilihat publik tanpa login) ----
Route::get('/shared/{uuid}', [SharedDocumentController::class, 'show']);
Route::get('/media/public/{uuid}', [MediaController::class, 'publicShow']);
Route::get('/blast-images/{uuid}', [NotificationController::class, 'showBlastImage']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/shared', [SharedDocumentController::class, 'store']);
    Route::put('/shared/{uuid}', [SharedDocumentController::class, 'update']);
    Route::delete('/shared/{uuid}', [SharedDocumentController::class, 'destroy']);
});

// ---- Project / Builder (payload dokumen disimpan sebagai JSONB di PostgreSQL) ----
Route::middleware('auth:sanctum')->prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);
    Route::get('/public', [ProjectController::class, 'publicIndex']);
    Route::get('/public/{uuid}', [ProjectController::class, 'publicShow']);
    Route::get('/{uuid}', [ProjectController::class, 'show']);
    Route::put('/{uuid}', [ProjectController::class, 'save']);
    Route::delete('/{uuid}', [ProjectController::class, 'destroy']);
    Route::post('/{uuid}/publish', [ProjectController::class, 'publish']);

    Route::get('/{uuid}/ai-results', [ProjectAiResultController::class, 'index']);
    Route::post('/{uuid}/ai-results', [ProjectAiResultController::class, 'store']);
    Route::delete('/{uuid}/ai-results/{result}', [ProjectAiResultController::class, 'destroy']);

    Route::get('/{uuid}/ai-chats', [AiChatController::class, 'index']);
    Route::post('/{uuid}/ai-chats', [AiChatController::class, 'store']);
    Route::get('/{uuid}/ai-chats/{session}', [AiChatController::class, 'show']);
    Route::post('/{uuid}/ai-chats/{session}/messages', [AiChatController::class, 'storeMessage']);
    Route::delete('/{uuid}/ai-chats/{session}', [AiChatController::class, 'destroy']);
});

// ---- Media (File Manager: gambar disimpan di disk publik) ----
Route::middleware('auth:sanctum')->prefix('media')->group(function () {
    Route::get('/', [MediaController::class, 'index']);
    Route::post('/', [MediaController::class, 'store']);
    Route::get('/files/{id}', [MediaController::class, 'show']);
    Route::delete('/{id}', [MediaController::class, 'destroy']);
});

// ---- Font kustom (TTF/OTF/WOFF/WOFF2, disimpan di object storage) ----
Route::middleware('auth:sanctum')->prefix('fonts')->group(function () {
    Route::get('/', [FontController::class, 'index']);
    Route::post('/', [FontController::class, 'store']);
    Route::delete('/{uuid}', [FontController::class, 'destroy']);
});

// Render HTML (dari preview) menjadi PDF via Chrome/Edge/Chromium headless.
// Dokumen di-chunk per halaman lalu digabung kembali (lihat PdfExportController).
Route::middleware('auth:sanctum')->post('/export/pdf', [PdfExportController::class, 'store']);
Route::middleware('auth:sanctum')->get('/export/pdf/{token}', [PdfExportController::class, 'status']);
Route::middleware('auth:sanctum')->get('/export/pdf/{token}/download', [PdfExportController::class, 'download']);

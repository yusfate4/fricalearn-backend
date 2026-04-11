<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// --- 🎮 Controllers ---
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\GamificationController;
use App\Http\Controllers\Api\LiveClassController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AnalyticsController; 
use App\Http\Controllers\Api\ParentAnalyticsController; 
use App\Http\Controllers\Api\Admin\AIQuizController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\AdminScheduleController;

// 🚀 THE YUSUF MIGRATION TOOL
Route::get('/force-migrate-7788', function () {
    Artisan::call('config:clear');
    Artisan::call('route:clear'); 
    try {
        Artisan::call('migrate', ['--force' => true]);
        return response()->json(['status' => 'Migration Attempted', 'output' => Artisan::output() ?: 'Success']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

/*
|--------------------------------------------------------------------------
| 🔓 Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/ai/active-schedule', [AdminScheduleController::class, 'getActiveSchedule']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
});

/*
|--------------------------------------------------------------------------
| 🔐 Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 🚀 THE CHAT FIX: Standardizing the message route for all roles
    Route::prefix('chat')->group(function () {
        Route::get('/conversations', [ChatController::class, 'getAdminConversations']); 
    Route::get('/chats', [ChatController::class, 'getAdminConversations']); // Alias for support inbox
    Route::get('/conversations/{id}/messages', [ChatController::class, 'getAdminMessages']); 
    Route::post('/conversations/{id}/read', [ChatController::class, 'markAsRead']);
    });

    /*
    |--------------------------------------------------------------------------
    | 👑 STAFF ROUTES (Admins & Tutors)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {

        // 📝 Tutor Profile Management
        Route::get('/tutor-profile', [AuthController::class, 'getTutorProfile']);
        Route::post('/tutor-profile', [AuthController::class, 'updateTutorProfile']);

        // 📊 Analytics & Stats
        Route::get('/analytics', [AnalyticsController::class, 'index']); // Fixes 404
        Route::get('/stats', [AnalyticsController::class, 'adminStats']);
        Route::get('/users', function() {
            return response()->json(\App\Models\User::where('role', 'student')->with('studentProfile')->get());
        });

        // Master Schedule & Live Classes
        Route::get('/schedule', [AdminScheduleController::class, 'getActiveSchedule']);
        Route::post('/update-schedule', [AdminScheduleController::class, 'updateSchedule']);
        Route::get('/live-classes', [LiveClassController::class, 'index']);
        Route::get('/live-classes/admin-data', [LiveClassController::class, 'adminData']);
        Route::post('/live-classes', [LiveClassController::class, 'store']);
        Route::delete('/live-classes/{id}', [LiveClassController::class, 'destroy']);

        // Content Management
        Route::apiResource('courses', CourseController::class)->except(['show']);
        Route::get('/lessons', [LessonController::class, 'index']);
        Route::post('/lessons', [LessonController::class, 'store']);
        Route::get('/lessons/{id}', [LessonController::class, 'show']);
        Route::put('/lessons/{id}', [LessonController::class, 'update']);
        Route::delete('/lessons/{id}', [LessonController::class, 'destroy']);
        Route::post('/lessons/{id}/content', [LessonController::class, 'uploadContent']);

        // Quiz Builder
        Route::get('/questions', [QuestionController::class, 'index']); 
        Route::post('/questions', [QuestionController::class, 'store']);
        Route::post('/ai/generate-quiz', [AIQuizController::class, 'generate']);

        // Staff Inbox
        Route::get('/chats', [ChatController::class, 'getAdminConversations']);

        /*
        |------------------------------------------------------------------
        | ⛔ FOUNDER ONLY (SuperAdmin)
        |------------------------------------------------------------------
        */
        Route::prefix('payments')->group(function () {
            Route::get('/pending', [PaymentController::class, 'getPendingPayments']);
            Route::get('/history', [PaymentController::class, 'getPaymentHistory']);
            Route::post('/{id}/approve', [PaymentController::class, 'approvePayment']);
            Route::post('/{id}/reject', [PaymentController::class, 'rejectPayment']);
        });

        Route::prefix('rewards')->group(function () {
            Route::get('/', [RewardController::class, 'index']);       
            Route::post('/', [RewardController::class, 'store']);
            Route::post('/{id}', [RewardController::class, 'update']); 
            Route::delete('/{id}', [RewardController::class, 'destroy']);
            Route::get('/redemptions', [GamificationController::class, 'getAllRedemptions']);
            Route::post('/redemptions/{id}/fulfill', [GamificationController::class, 'fulfillRedemption']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | 👨‍👩‍👧‍👦 PARENT PORTAL
    |--------------------------------------------------------------------------
    */
    Route::prefix('parent')->group(function () {
        Route::get('/dashboard', [ParentController::class, 'getDashboardData']);
        Route::get('/children', [ParentController::class, 'getChildren']);
        Route::post('/register-child', [ParentController::class, 'registerChild']);
        Route::post('/submit-payment', [PaymentController::class, 'submitPayment']);
        Route::get('/child-stats/{childId}', [ParentAnalyticsController::class, 'getChildStats']);
    });

    /*
    |----------------------------------------------------------------------
    | 🛡️ VERIFIED ONLY (Students)
    |----------------------------------------------------------------------
    */
    Route::middleware(['verified'])->group(function () {
        Route::get('/courses', [CourseController::class, 'index']);
        Route::get('/courses/{id}', [CourseController::class, 'show']);
        
        Route::prefix('lessons')->group(function () {
            Route::get('/{id}', [LessonController::class, 'show']);
            Route::post('/{id}/complete', [LessonController::class, 'complete']);
        });

        Route::post('/ai/chat-olu', [AiController::class, 'chatWithOlu']);
        
        Route::prefix('gamification')->group(function () {
            Route::get('/leaderboard', [GamificationController::class, 'getLeaderboard']);
            Route::get('/rewards', [GamificationController::class, 'getRewardsCatalog']);
            Route::post('/rewards/{id}/redeem', [GamificationController::class, 'redeemReward']);
        });
    });
});
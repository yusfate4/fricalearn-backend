<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- 🎮 Import All Controllers ---
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
use App\Http\Controllers\Api\Student\AIHintController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RewardController;

/*
|--------------------------------------------------------------------------
| 🔓 Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/public/analytics/{studentId}', [AnalyticsController::class, 'publicStudentStats']);

// 📅 AI Global Schedule (Publicly viewable for timer math)
Route::get('/ai/active-schedule', [AiController::class, 'getActiveSchedule']);

/*
|--------------------------------------------------------------------------
| 🔐 Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // 👤 --- AUTH & IDENTITY ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // 📚 --- COURSES & LESSONS ---
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    
    Route::prefix('lessons')->group(function () {
        Route::get('/{id}', [LessonController::class, 'show']);
        Route::post('/{id}/complete', [LessonController::class, 'complete']);
        Route::get('/{id}/questions', [LessonController::class, 'getQuestions']); 
    });

    // 📊 --- ANALYTICS & AI HELPERS ---
    Route::get('/student/analytics', [AnalyticsController::class, 'studentStats']);
    Route::post('/ai/hint', [AIHintController::class, 'getHint']);
    Route::post('/ai/chat-olu', [AiController::class, 'chatWithOlu']);
    
    /** * 🎙️ AI PRONUNCIATION: Now handles Whisper transcription & Silence check
     * Note: We moved this here to ensure it is protected by Sanctum
     */
    Route::post('/ai/verify-pronunciation', [AiController::class, 'verifyPronunciation']);

    // 🏆 --- GAMIFICATION (Student View) ---
    Route::prefix('gamification')->group(function () {
        Route::get('/leaderboard', [GamificationController::class, 'getLeaderboard']);
        Route::get('/rewards', [GamificationController::class, 'getRewardsCatalog']);
        Route::get('/my-rewards', [GamificationController::class, 'getMyRewards']);
        Route::post('/rewards/{id}/redeem', [GamificationController::class, 'redeemReward']);
        Route::post('/earn', [GamificationController::class, 'earn']);
    });

    // 💬 --- CHAT SYSTEM (Student/Parent View) ---
    Route::prefix('chat')->group(function () {
        Route::get('/conversation', [ChatController::class, 'getConversation']);
        Route::post('/message', [ChatController::class, 'sendMessage']);
    });

    // 👨‍👩‍👧‍👦 --- PARENT PORTAL ---
    Route::prefix('parent')->group(function () {
        Route::get('/dashboard', [ParentController::class, 'getDashboardData']);
        Route::get('/children', [ParentController::class, 'getChildren']);
        Route::post('/register-child', [ParentController::class, 'registerChild']);
        Route::post('/switch-to-child/{childId}', [ParentController::class, 'switchToChild']);
        Route::get('/active-student/{id}', [ParentController::class, 'getActiveStudentProfile']);
        Route::get('/child-stats/{childId}', [ParentAnalyticsController::class, 'getChildStats']);
        Route::post('/payments/submit', [PaymentController::class, 'submitPayment']); 
    });

    // Shared Features
    Route::get('/parent/courses', [CourseController::class, 'getParentCourses']);
    Route::get('/live-classes', [LiveClassController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | 👑 ADMIN ROUTES
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        
        // 📈 Admin Dashboard Stats
        Route::get('/stats', [AnalyticsController::class, 'adminStats']);

        // 💬 --- ADMIN CHAT MANAGEMENT ---
        Route::prefix('conversations')->group(function () {
            Route::get('/', [ChatController::class, 'getAdminConversations']); 
            Route::get('/{id}/messages', [ChatController::class, 'getAdminMessages']); // Fetch full chat
            Route::post('/{id}/read', [ChatController::class, 'markAsRead']);         // Set read status
        });

        // 🏗️ --- COURSE & LESSON MANAGEMENT ---
        Route::prefix('courses')->group(function () {
            Route::get('/', [CourseController::class, 'index']); // 👈 Fixed: Now allows GET for Question dropdowns
            Route::post('/', [CourseController::class, 'store']);
            Route::post('/{id}', [CourseController::class, 'update']); 
            Route::delete('/{id}', [CourseController::class, 'destroy']);
        });

        Route::prefix('lessons')->group(function () {
            Route::get('/', [LessonController::class, 'index']);
            Route::post('/', [LessonController::class, 'store']);
            Route::post('/{id}', [LessonController::class, 'update']); 
            Route::delete('/{id}', [LessonController::class, 'destroy']); 
        });

        // 📝 --- QUIZ & AI TOOLS ---
        Route::get('/questions', [QuestionController::class, 'index']); // Added for management table
        Route::post('/questions', [QuestionController::class, 'store']);
        Route::post('/ai/generate-quiz', [AIQuizController::class, 'generate']);
        Route::post('/update-schedule', [AiController::class, 'updateSchedule']);

        // 💰 --- PAYMENT & SUBSCRIPTION MANAGEMENT ---
        Route::prefix('payments')->group(function () {
            Route::get('/pending', [PaymentController::class, 'getPendingPayments']);
            Route::get('/history', [PaymentController::class, 'getPaymentHistory']);
            Route::post('/{id}/approve', [PaymentController::class, 'approvePayment']);
            Route::post('/{id}/reject', [PaymentController::class, 'rejectPayment']);
        });

        // 🎁 --- REWARDS & MARKETPLACE MANAGEMENT ---
        Route::prefix('rewards')->group(function () {
            Route::get('/', [RewardController::class, 'index']);       // 👈 Admin Management
            Route::post('/', [RewardController::class, 'store']);
            Route::post('/{id}', [RewardController::class, 'update']); 
            Route::delete('/{id}', [RewardController::class, 'destroy']);
        });

        // 🛒 Fulfillment logic (Purchases made by students)
        Route::get('/redemptions', [GamificationController::class, 'getAllRedemptions']);
        Route::post('/redemptions/{id}/fulfill', [GamificationController::class, 'fulfillRedemption']);

        // 🎥 --- LIVE CLASSES & USER MANAGEMENT ---
        Route::post('/live-classes', [LiveClassController::class, 'store']);
        Route::get('/users', function() {
            return response()->json(\App\Models\User::with('studentProfile')->get());
        });
    });
});

/*
|--------------------------------------------------------------------------
| 🚪 Fallback Unauthorized Route
|--------------------------------------------------------------------------
*/
Route::get('/unauthorized', function () {
    return response()->json(['message' => 'Unauthorized.'], 401);
})->name('login');
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import all controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\GamificationController;
use App\Http\Controllers\Api\LiveClassController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AnalyticsController; 
use App\Http\Controllers\Api\Admin\AIQuizController;
use App\Http\Controllers\Api\Student\AIHintController;
use App\Http\Controllers\Api\Student\PronunciationController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 👨‍👩‍👧‍👦 PUBLIC PARENT VIEW
Route::get('/public/analytics/{studentId}', [AnalyticsController::class, 'publicStudentStats']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth Management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // 📊 Student Personal Analytics
    Route::get('/student/analytics', [AnalyticsController::class, 'studentStats']);

    // ✨ AI Tools for Students (Phase 2)
    Route::post('/ai/hint', [AIHintController::class, 'getHint']);
    Route::post('/ai/verify-pronunciation', [PronunciationController::class, 'verify']);

    // Courses & Lessons (Student Access)
    Route::prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::get('/{id}', [CourseController::class, 'show']);
        Route::post('/{id}/enroll', [CourseController::class, 'enroll']);
        Route::get('/my/enrollments', [CourseController::class, 'myEnrollments']);
    });

    Route::prefix('lessons')->group(function () {
        Route::get('/{id}', [LessonController::class, 'show']);
        Route::post('/{id}/start', [LessonController::class, 'start']);
        Route::post('/{id}/complete', [LessonController::class, 'complete']);
        Route::get('/{id}/questions', [LessonController::class, 'getQuestions']); 
    });
    
    // Student Chat System
    Route::prefix('chat')->group(function () {
        Route::get('/conversation', [ChatController::class, 'getConversation']);
        Route::post('/message', [ChatController::class, 'sendMessage']);
    });

    // 🎁 Gamification & Rewards (Student Side)
    Route::prefix('gamification')->group(function () {
        Route::get('/leaderboard', [GamificationController::class, 'getLeaderboard']);
        Route::get('/rewards', [GamificationController::class, 'getRewards']);
        Route::post('/rewards/{id}/redeem', [GamificationController::class, 'redeemReward']);
    });

    /**
     * 🚀 FALLBACK ROUTES
     */
    Route::get('/rewards', [GamificationController::class, 'getRewards']);
    Route::get('/leaderboard', [GamificationController::class, 'getLeaderboard']);

    /*
    |--------------------------------------------------------------------------
    | Admin-Only Protected Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {
        
        // Matches: api/lessons/{id}/content
        Route::post('/lessons/{id}/content', [LessonController::class, 'uploadContent']);
    
        Route::prefix('admin')->group(function () {
            
            // 📊 Admin Stats
            Route::get('/stats', [AnalyticsController::class, 'adminStats']);

            // 👨‍🎓 User Management
            Route::get('/users', function() {
                return response()->json(\App\Models\User::with('studentProfile')->get());
            });
            Route::get('/analytics/{userId}', [AnalyticsController::class, 'studentStats']);

            // 📝 Subject Management
            Route::get('/courses', [CourseController::class, 'index']); 
            Route::post('/courses', [CourseController::class, 'store']);
            Route::put('/courses/{id}', [CourseController::class, 'update']);
            
            // 📝 Lesson Management
            Route::get('/lessons', [LessonController::class, 'index']);
            Route::post('/lessons', [LessonController::class, 'store']);
            Route::put('/lessons/{id}', [LessonController::class, 'update']); 
            Route::delete('/lessons/{id}', [LessonController::class, 'destroy']); 
            
            Route::post('/questions', [QuestionController::class, 'store']);

            // 🤖 Admin AI Tools
            Route::post('/ai/generate-quiz', [AIQuizController::class, 'generate']);

            // 📦 Reward Management
            Route::get('/rewards', [GamificationController::class, 'getRewards']);
            Route::get('/reward-redemptions', [GamificationController::class, 'getAllRedemptions']);
            Route::put('/reward-redemptions/{id}/fulfill', [GamificationController::class, 'fulfillRedemption']);

            /**
             * 💬 ADMIN CHAT SYSTEM
             */
            Route::get('/conversations', [ChatController::class, 'getAdminConversations']);
            Route::get('/conversations/{id}/messages', [ChatController::class, 'getMessages']);
            // 🚀 FIXED: Added the 'read' endpoint to resolve the 404
            Route::post('/conversations/{id}/read', [ChatController::class, 'markAsRead']);
        });
    });
});

// Fallback for unauthorized access
Route::get('/unauthorized', function () {
    return response()->json(['message' => 'Unauthorized. Please login.'], 401);
})->name('login');
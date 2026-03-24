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

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/courses/{id}', [CourseController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth Management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Courses
    Route::prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::post('/', [CourseController::class, 'store']);
        Route::put('/{id}', [CourseController::class, 'update']);
        Route::post('/{id}/enroll', [CourseController::class, 'enroll']);
        Route::get('/my/enrollments', [CourseController::class, 'myEnrollments']);
    });

    // Lessons
    Route::prefix('lessons')->group(function () {
        Route::get('/{id}', [LessonController::class, 'show']);
        Route::post('/{id}/start', [LessonController::class, 'start']);
        Route::post('/{id}/complete', [LessonController::class, 'complete']);
        Route::post('/{id}/content', [LessonController::class, 'uploadContent']);
    });
    
    // Quizzes
    Route::prefix('quizzes')->group(function () {
        Route::get('/{id}', [QuizController::class, 'show']);
        Route::post('/{id}/start', [QuizController::class, 'startAttempt']);
        Route::post('/attempts/{id}/submit', [QuizController::class, 'submitAttempt']);
    });
    
    Route::post('/admin/questions', [App\Http\Controllers\Api\QuestionController::class, 'store']);
    
    // Assignments
    Route::prefix('assignments')->group(function () {
        Route::get('/{id}', [AssignmentController::class, 'show']);
        Route::post('/{id}/submit', [AssignmentController::class, 'submit']);
        Route::post('/submissions/{id}/grade', [AssignmentController::class, 'grade']);
    });

    // Gamification & Rewards (Student Side)
    Route::prefix('gamification')->group(function () {
        Route::get('/leaderboard', [GamificationController::class, 'getLeaderboard']);
        Route::get('/badges', [GamificationController::class, 'getBadges']);
    });

    Route::get('/rewards', [GamificationController::class, 'getRewards']);
    Route::post('/rewards/{id}/redeem', [GamificationController::class, 'redeemReward']);
    Route::get('/my-rewards', [GamificationController::class, 'getMyRedemptions']);

    // Live Classes
    Route::prefix('live-classes')->group(function () {
        Route::get('/', [LiveClassController::class, 'index']);
        Route::post('/', [LiveClassController::class, 'store']);
    });

    // Parent Routes
    Route::prefix('parent')->group(function () {
        Route::get('/children', [ParentController::class, 'getChildren']);
        Route::post('/link-child', [ParentController::class, 'linkChild']);
        Route::get('/child-progress', function (Request $request) {
            return response()->json($request->user()->load('studentProfile'));
        });
    });

    // Progress & Analytics
    Route::prefix('progress')->group(function () {
        Route::get('/analytics', [ProgressController::class, 'analytics']);
    });

    // Chat System Routes
    Route::get('/chat/conversation', [\App\Http\Controllers\Api\ChatController::class, 'getConversation']);
    Route::post('/chat/message', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (Founder's Control Room)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        
        Route::get('/stats', function () {
            return response()->json([
                'total_students' => \App\Models\User::where('is_admin', false)->count(),
                'total_lessons' => \App\Models\Lesson::count(),
                'total_questions' => \App\Models\Question::count(),
                'total_courses' => \App\Models\Course::count(),
            ]);
        });

        Route::get('/courses', [CourseController::class, 'index']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{id}', [CourseController::class, 'update']);
        
        Route::get('/lessons', [LessonController::class, 'index']);
        Route::post('/lessons', [LessonController::class, 'store']);

        // Reward Management (Admin Side)
        Route::get('/reward-redemptions', [GamificationController::class, 'getAllRedemptions']);
        Route::put('/reward-redemptions/{id}/fulfill', [GamificationController::class, 'fulfillRedemption']);
        Route::post('/rewards', [GamificationController::class, 'store']);

        // Conversation Management for Admin
        Route::get('/conversations', [\App\Http\Controllers\Api\ChatController::class, 'getAllConversations']);
  
        Route::post('/conversations/{id}/read', [ChatController::class, 'markAsRead']);
  
        });

}); // Closes auth:sanctum group

// Fallback for unauthorized access
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized. Please login.'], 401);
})->name('login');
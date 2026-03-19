<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import all your controllers here
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

// Move this here TEMPORARILY to test the Course Page
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
        // Route::get('/{id}', [CourseController::class, 'show']);
        Route::post('/', [CourseController::class, 'store']);
        Route::put('/{id}', [CourseController::class, 'update']);
        Route::post('/{id}/enroll', [CourseController::class, 'enroll']);
        Route::get('/my/enrollments', [CourseController::class, 'myEnrollments']);
    }); // Fixed: Properly closed courses group

    // Lessons
    Route::prefix('lessons')->group(function () {
        Route::get('/{id}', [LessonController::class, 'show']);
        Route::post('/{id}/start', [LessonController::class, 'start']);
        Route::post('/{id}/complete', [LessonController::class, 'complete']);
        
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

    // Gamification
    Route::prefix('gamification')->group(function () {
        Route::get('/leaderboard', [GamificationController::class, 'getLeaderboard']);
        Route::get('/badges', [GamificationController::class, 'getBadges']);
    });

    // Live Classes
    Route::prefix('live-classes')->group(function () {
        Route::get('/', [LiveClassController::class, 'index']);
        Route::post('/', [LiveClassController::class, 'store']);
    });

    // Parent Routes
    Route::prefix('parent')->group(function () {
        Route::get('/children', [ParentController::class, 'getChildren']);
        Route::post('/link-child', [ParentController::class, 'linkChild']);
    });
    
    Route::get('/parent/child-progress', function (Request $request) {
    // For MVP: We will return the current user's data 
    // (Assuming the parent is logged in as the student for now, 
    // or we can fetch a specific student ID)
    return response()->json($request->user()->load('studentProfile'));
});

    // Progress & Analytics
    Route::prefix('progress')->group(function () {
        Route::get('/analytics', [ProgressController::class, 'analytics']);
    });


    /*
    |--------------------------------------------------------------------------
    | Admin Routes (Founder's Control Room)
    |--------------------------------------------------------------------------
    | These routes require both a valid login AND is_admin = true.
    | Handles Requirements: LMS-01, LMS-10, HL-06
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        
        // 📊 Admin Stats (For your Control Room Dashboard)
        Route::get('/stats', function () {
            return response()->json([
                'total_students' => \App\Models\User::where('is_admin', false)->count(),
                'total_lessons' => \App\Models\Lesson::count(),
                'total_questions' => \App\Models\Question::count(),
                'total_courses' => \App\Models\Course::count(),
            ]);
        });

        // 📚 Course Management (LMS-01)
        Route::get('/courses', [CourseController::class, 'index']); // Get all for list
        Route::post('/courses', [CourseController::class, 'store']); // Create new
        Route::put('/courses/{id}', [CourseController::class, 'update']); // Edit
        
        // 📝 Lesson & Content Management (LMS-02, LMS-10)
        // This will eventually handle PDF and PPT uploads
        Route::get('/lessons', [LessonController::class, 'index']);
        Route::post('/lessons', [LessonController::class, 'store']);
    });
});

// At the very bottom of routes/api.php
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized. Please login.'], 401);
})->name('login');
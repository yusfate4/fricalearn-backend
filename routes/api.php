<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

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
use App\Http\Controllers\Api\AdminScheduleController;

// 🚀 THE YUSUF MIGRATION TOOL (Bypass Cache)
Route::get('/force-migrate-7788', function () {
    Artisan::call('config:clear');
    Artisan::call('route:clear'); 
    try {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        return response()->json(['status' => 'Migration Attempted', 'output' => $output ?: 'Already up to date']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

/*
|--------------------------------------------------------------------------
| 🔓 Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/contact', function (Request $request) {
    $data = $request->validate([
        'name' => 'required|string', 'email' => 'required|email',
        'role' => 'required|string', 'message' => 'required|string',
    ]);
    Mail::raw("New Message from FricaLearn:\n\nName: {$data['name']}\nEmail: {$data['email']}\nRole: {$data['role']}\nMessage: {$data['message']}", function ($message) use ($data) {
            $message->to('hello@fricalearn.com')->subject('New Contact Form Submission: ' . $data['name']);
    });
    return response()->json(['message' => 'Message sent successfully!']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
});

Route::get('/public/analytics/{studentId}', [AnalyticsController::class, 'publicStudentStats']);
Route::get('/ai/active-schedule', [AdminScheduleController::class, 'getActiveSchedule']);

/*
|--------------------------------------------------------------------------
| 🔐 Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | 👑 STAFF ROUTES (Admins & Tutors)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Shared Dashboard Access
        Route::get('/stats', [AnalyticsController::class, 'adminStats']);
        Route::get('/users', function() {
            return response()->json(\App\Models\User::with('studentProfile')->get());
        });

        // Master Schedule & Live Classes
        Route::get('/schedule', [AdminScheduleController::class, 'getActiveSchedule']);
        Route::post('/update-schedule', [AdminScheduleController::class, 'updateSchedule']);
        Route::post('/live-classes', [LiveClassController::class, 'store']);

        // Content & Curriculum
        Route::prefix('courses')->group(function () {
            Route::get('/', [CourseController::class, 'index']); 
            Route::post('/', [CourseController::class, 'store']);
            Route::post('/{id}', [CourseController::class, 'update']); 
            Route::delete('/{id}', [CourseController::class, 'destroy']);
        });

        Route::prefix('lessons')->group(function () {
            Route::get('/', [LessonController::class, 'index']);
            Route::post('/', [LessonController::class, 'store']);
            Route::post('/{id}', [LessonController::class, 'update']); 
            Route::delete('/{id}', [LessonController::class, 'destroy']); 
            Route::post('/{id}/content', [LessonController::class, 'uploadContent']);
        });

        // Quiz Builder
        Route::get('/questions', [QuestionController::class, 'index']); 
        Route::post('/questions', [QuestionController::class, 'store']);
        Route::post('/ai/generate-quiz', [AIQuizController::class, 'generate']);

        // Conversations
        Route::prefix('conversations')->group(function () {
            Route::get('/', [ChatController::class, 'getAdminConversations']); 
            Route::get('/{id}/messages', [ChatController::class, 'getAdminMessages']); 
            Route::post('/{id}/read', [ChatController::class, 'markAsRead']);         
        });

        /*
        |------------------------------------------------------------------
        | ⛔ FOUNDER ONLY (Super Admin)
        |------------------------------------------------------------------
        */
        Route::prefix('payments')->group(function () {
            Route::get('/pending', [PaymentController::class, 'getPendingPayments']);
            Route::get('/history', [PaymentController::class, 'getPaymentHistory']);
            Route::post('/{id}/approve', [PaymentController::class, 'approvePayment']);
            Route::post('/{id}/reject', [PaymentController::class, 'rejectPayment']);
        });

        Route::get('/redemptions', [GamificationController::class, 'getAllRedemptions']);
        Route::post('/redemptions/{id}/fulfill', [GamificationController::class, 'fulfillRedemption']);
        
        Route::prefix('rewards')->group(function () {
            Route::get('/', [RewardController::class, 'index']);       
            Route::post('/', [RewardController::class, 'store']);
            Route::post('/{id}', [RewardController::class, 'update']); 
            Route::delete('/{id}', [RewardController::class, 'destroy']);
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
        Route::post('/switch-to-child/{childId}', [ParentController::class, 'switchToChild']);
        Route::get('/active-student/{id}', [ParentController::class, 'getActiveStudentProfile']);
        Route::get('/child-stats/{childId}', [ParentAnalyticsController::class, 'getChildStats']);
        Route::post('/submit-payment', [PaymentController::class, 'submitPayment']);
    });

    /*
    |----------------------------------------------------------------------
    | 🛡️ VERIFIED ONLY ROUTES (Students)
    |----------------------------------------------------------------------
    */
    Route::middleware(['verified'])->group(function () {
        Route::prefix('lessons')->group(function () {
            Route::get('/{id}', [LessonController::class, 'show']);
            Route::post('/{id}/complete', [LessonController::class, 'complete']);
            Route::get('/{id}/questions', [LessonController::class, 'getQuestions']); 
        });

        Route::get('/student/analytics', [AnalyticsController::class, 'studentStats']);
        Route::post('/ai/hint', [AIHintController::class, 'getHint']);
        Route::post('/ai/chat-olu', [AiController::class, 'chatWithOlu']);
        Route::post('/ai/verify-pronunciation', [AiController::class, 'verifyPronunciation']);

        Route::prefix('gamification')->group(function () {
            Route::get('/leaderboard', [GamificationController::class, 'getLeaderboard']);
            Route::get('/rewards', [GamificationController::class, 'getRewardsCatalog']);
            Route::get('/my-rewards', [GamificationController::class, 'getMyRewards']);
            Route::post('/rewards/{id}/redeem', [GamificationController::class, 'redeemReward']);
            Route::post('/earn', [GamificationController::class, 'earn']);
        });

        Route::get('/live-classes', [LiveClassController::class, 'index']);
    });
});

/*
|--------------------------------------------------------------------------
| 📧 Verification & Utils
|--------------------------------------------------------------------------
*/
Route::get('/email/verify/{id}/{hash}', function (Request $request) {
    if (!$request->hasValidSignature()) return response()->json(["message" => "Invalid link."], 403);
    $user = \App\Models\User::findOrFail($request->route('id'));
    if ($user->hasVerifiedEmail()) return response()->json(["message" => "Already verified."]);
    if ($user->markEmailAsVerified()) event(new \Illuminate\Auth\Events\Verified($user));
    return response()->json(["message" => "Email verified!"]);
})->name('verification.verify');

Route::get('/unauthorized', function () {
    return response()->json(['message' => 'Unauthorized.'], 401);
})->name('login');
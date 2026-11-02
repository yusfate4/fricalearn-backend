<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

// --- 🎮 Controllers ---
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\GamificationController;
use App\Http\Controllers\Api\AdminController;
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
use App\Http\Controllers\Api\ExternalSubjectController;
use App\Http\Controllers\Api\ExternalLessonController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\MonthlyReportController;
use App\Http\Controllers\Api\ChildProgressController;
use App\Http\Controllers\Api\OakCurriculumController;


// 🔍 RECEIPT UPLOAD DIAGNOSTIC — POST a test file to this route
// curl -X POST https://api.fricalearn.com/api/upload-test-7788 -F "file=@/path/to/image.png"
Route::post('/upload-test-7788', function (\Illuminate\Http\Request $request) {
    $info = [
        'php_upload_max'     => ini_get('upload_max_filesize'),
        'php_post_max'       => ini_get('post_max_size'),
        'php_tmp_dir'        => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        'tmp_dir_writable'   => is_writable(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
        'storage_writable'   => is_writable(storage_path('app/public/receipts')),
        'has_file'           => $request->hasFile('file'),
    ];
    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $info['file_valid']   = $file->isValid();
        $info['file_error']   = $file->getError();
        $info['file_size']    = $file->getSize();
        $info['file_mime']    = $file->getMimeType();
        try {
            $path = $file->storeAs('receipts', 'test_upload_' . time() . '.' . $file->getClientOriginalExtension(), 'public');
            $info['stored_path']   = $path;
            $info['file_exists']   = \Illuminate\Support\Facades\Storage::disk('public')->exists($path);
            $info['public_url']    = asset('storage/' . $path);
        } catch (\Exception $e) {
            $info['store_error'] = $e->getMessage();
        }
    }
    return response()->json($info);
});

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

// 🔗 STORAGE LINK + RECEIPTS FOLDER TOOL — run once to fix receipt 404s
// Visit: https://api.fricalearn.com/api/storage-link-7788
Route::get('/storage-link-7788', function () {
    try {
        $results = [];

        // 1. Ensure storage symlink exists
        $linkPath = public_path('storage');
        if (is_link($linkPath)) {
            $results['symlink'] = 'Already linked → ' . readlink($linkPath);
        } else {
            Artisan::call('storage:link', ['--force' => true]);
            $results['symlink'] = 'Created: ' . (Artisan::output() ?: 'Done');
        }

        // 2. Ensure receipts directory exists inside storage/app/public
        $receiptsDir = storage_path('app/public/receipts');
        if (!is_dir($receiptsDir)) {
            mkdir($receiptsDir, 0775, true);
            $results['receipts_dir'] = 'Created: ' . $receiptsDir;
        } else {
            $results['receipts_dir'] = 'Already exists: ' . $receiptsDir;
        }

        // 3. Ensure uploads directory exists (for onboarding receipts)
        $uploadsDir = storage_path('app/public/uploads');
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0775, true);
            $results['uploads_dir'] = 'Created: ' . $uploadsDir;
        } else {
            $results['uploads_dir'] = 'Already exists: ' . $uploadsDir;
        }

        return response()->json(['status' => 'OK', 'details' => $results]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

/*
|--------------------------------------------------------------------------
| 🔓 Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/contact', [AuthController::class, 'handleContactForm']);
Route::get('/ai/active-schedule', [AdminScheduleController::class, 'getActiveSchedule']);

Route::prefix('auth')->group(function () {
    // ── Rate limit: max 5 registration attempts per IP per minute ──
    Route::middleware('throttle:5,1')->post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
});

// ── Email verification (signed URL — clicked from the parent's inbox) ──
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');


// OAK CURRICULUM (Subjects)
Route::prefix('oak')->middleware('auth:sanctum')->group(function () {
    Route::get('/test',           [OakCurriculumController::class, 'testConnection']);
    Route::get('/key-stages',     [OakCurriculumController::class, 'getKeyStages']);
    Route::get('/subjects',       [OakCurriculumController::class, 'getSubjects']);
    Route::get('/units',          [OakCurriculumController::class, 'getUnits']);
    Route::get('/lessons',        [OakCurriculumController::class, 'getLessons']);
    Route::get('/lesson-detail',  [OakCurriculumController::class, 'getLessonDetail']);
    Route::get('/lesson-quiz',    [OakCurriculumController::class, 'getLessonQuiz']);
    Route::get('/search',         [OakCurriculumController::class, 'searchLessons']);
});
// Curriculum routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-curriculum', function (Request $request) {
        $user = $request->user();
        
        return DB::table('user_enrolled_subjects_filtered')
            ->where('user_id', $user->id)
            ->get();
    });
});

/*
|--------------------------------------------------------------------------
| 🔐 Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 🚀 GLOBAL CHAT
    Route::prefix('chat')->group(function () {
        Route::post('/message',              [ChatController::class, 'sendMessage']);
        Route::get('/conversation',          [ChatController::class, 'getConversation']); // ParentMessages
        Route::get('/conversations',         [ChatController::class, 'getConversations']);
        Route::get('/messages/{receiverId}', [ChatController::class, 'getMessages']);
    });


Route::get('/students/{id}/info', function($id) {
    $student = \App\Models\User::where('role', 'student')->findOrFail($id);
    return response()->json([
        'success' => true,
        'name' => $student->name,
        'id' => $student->id
    ]);
});
   

    /*
    |--------------------------------------------------------------------------
    | 👑 STAFF ROUTES (Admins & Tutors)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index']); 
        Route::get('/stats', [AnalyticsController::class, 'adminStats']);
        Route::get('/users', function() {
            return response()->json(\App\Models\User::where('role', 'student')->with('studentProfile')->get());
        });

        // ── Unified Admin API ─────────────────────────────────────────
        Route::get('/overview',              [AdminController::class, 'overview']);
        Route::get('/students',              [AdminController::class, 'students']);
        Route::get('/parents-list',          [AdminController::class, 'parents']);
        Route::get('/full-analytics',        [AdminController::class, 'analytics']);
        Route::get('/payments-overview',     [AdminController::class, 'paymentsOverview']);
        Route::get('/questions-list',        [AdminController::class, 'questions']);
        Route::put('/questions/{id}',        [AdminController::class, 'updateQuestion']);
        Route::delete('/questions/{id}',     [AdminController::class, 'deleteQuestion']);
        Route::post('/conversations/{id}/reply', [AdminController::class, 'replyToChat']);

        Route::get('/tutor-profile', [AuthController::class, 'getTutorProfile']);
        Route::post('/tutor-profile', [AuthController::class, 'updateTutorProfile']);
        
        Route::get('/conversations', [ChatController::class, 'getAdminConversations']); 
        Route::get('/chats', [ChatController::class, 'getAdminConversations']);
        Route::get('/conversations/{id}/messages', [ChatController::class, 'getAdminMessages']); 
        Route::post('/conversations/{id}/read', [ChatController::class, 'markAsRead']);

        Route::get('/redemptions', [GamificationController::class, 'getAllRedemptions']);

        Route::get('/schedule', [AdminScheduleController::class, 'getActiveSchedule']);
        Route::post('/update-schedule', [AdminScheduleController::class, 'updateSchedule']);
        Route::get('/live-classes', [LiveClassController::class, 'index']);
        Route::get('/live-classes/admin-data', [LiveClassController::class, 'adminData']);
        Route::post('/live-classes', [LiveClassController::class, 'store']);
        Route::delete('/live-classes/{id}', [LiveClassController::class, 'destroy']);

        Route::apiResource('courses', CourseController::class)->except(['show']);
        Route::get('/lessons', [LessonController::class, 'index']);
        Route::post('/lessons', [LessonController::class, 'store']);
        Route::get('/lessons/{id}', [LessonController::class, 'show']);
        Route::put('/lessons/{id}', [LessonController::class, 'update']);
        Route::delete('/lessons/{id}', [LessonController::class, 'destroy']);
        Route::post('/lessons/{id}/content', [LessonController::class, 'uploadContent']);

        Route::get('/questions', [QuestionController::class, 'index']); 
        Route::post('/questions', [QuestionController::class, 'store']);
        Route::post('/ai/generate-quiz', [AIQuizController::class, 'generate']);

        Route::prefix('payments')->group(function () {
            Route::get('/',          [PaymentController::class, 'getPendingPayments']); // AdminPayments overview
            Route::get('/pending',   [PaymentController::class, 'getPendingPayments']);
            Route::get('/history',   [PaymentController::class, 'getPaymentHistory']);
            Route::post('/{id}/approve', [PaymentController::class, 'approvePayment']);
            Route::post('/{id}/reject',  [PaymentController::class, 'rejectPayment']);
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
        Route::get('/courses', [CourseController::class, 'index']); 
        Route::get('/active-student/{id}', [ParentController::class, 'getActiveStudent']);
        Route::get('/student-analytics/{childId}', [MonthlyReportController::class, 'getStudentAnalytics']);
    Route::post('/send-monthly-report/{childId}', [MonthlyReportController::class, 'emailMonthlyReport']);
    Route::get('/child/{childId}/progress', [ChildProgressController::class, 'getProgress']);
    });


    /*
|--------------------------------------------------------------------------
| 🚀 NEW ONBOARDING ROUTES (4-Step Registration)
|--------------------------------------------------------------------------
| Flow: Course Selection → Grade Selection → Pricing → Payment Upload
| Features: Auto-approval, Maths/English (paid), Languages (free)
*/
Route::prefix('onboarding')->group(function () {
    // Step 1: Get available courses (Maths, English, Yoruba, Hausa, Igbo)
    Route::get('/courses', [OnboardingController::class, 'getCourses']);
    
    // Step 2: Calculate pricing based on selected courses
    Route::post('/calculate-pricing', [OnboardingController::class, 'calculatePricing']);
    
    // Step 3: Get bank account details for payment
    Route::get('/bank-details', [OnboardingController::class, 'getBankDetails']);
    
    // Step 4: Submit complete onboarding (creates child, uploads receipt, auto-approves, enrolls)
    Route::post('/submit', [OnboardingController::class, 'submitOnboarding']);
});
    /*
    |----------------------------------------------------------------------
    | 🎓 STUDENT ROUTES (No longer gated by 'verified' middleware)
    |----------------------------------------------------------------------
    */
    Route::get('/live-classes', [LiveClassController::class, 'index']);
    Route::get('/live-classes/{id}', [LiveClassController::class, 'show']);
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/enrolled', [CourseController::class, 'getEnrolledCourses']); // Get MY enrolled courses
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    
    Route::prefix('lessons')->group(function () {
        Route::get('/{id}', [LessonController::class, 'show']);
        Route::post('/{id}/complete', [LessonController::class, 'complete']);
    });

    Route::post('/ai/chat-olu', [AiController::class, 'chatWithOlu']);
    
    Route::prefix('gamification')->group(function () {
        Route::get('/leaderboard',           [GamificationController::class, 'getLeaderboard']);
        Route::get('/rewards',               [GamificationController::class, 'getRewardsCatalog']);
        Route::post('/rewards/{id}/redeem',  [GamificationController::class, 'redeemReward']);
        Route::get('/my-rewards',            [GamificationController::class, 'getMyRewards']);
        Route::get('/my-collection',         [GamificationController::class, 'getMyRewards']);
    });

    // Standalone aliases used by Dashboard + store pages
    Route::get('/my-rewards',   [GamificationController::class, 'getMyRewards']);
    Route::get('/my-treasures', [GamificationController::class, 'getMyRewards']);


 
// External subjects (Maths/English)
Route::prefix('external')->middleware('auth:sanctum')->group(function () {
    
    // Get user's enrolled subjects
    Route::get('/subjects', [ExternalSubjectController::class, 'index']);
    
    // Get subject details with topics
    Route::get('/subjects/{id}', [ExternalSubjectController::class, 'show']);
    
    // Get lessons for a topic
    Route::get('/topics/{id}/lessons', [ExternalLessonController::class, 'indexByTopic']);
    
    // Get single lesson
    Route::get('/lessons/{id}', [ExternalLessonController::class, 'show']);
    
    // Update lesson progress
    Route::post('/lessons/{id}/progress', [ExternalLessonController::class, 'updateProgress']);
    
    // Submit quiz
    Route::post('/lessons/{id}/quiz', [ExternalLessonController::class, 'submitQuiz']);

    Route::get('/users/{id}', function($id) {
    $user = \App\Models\User::findOrFail($id);
    return response()->json([
        'success' => true,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ]
    ]);
})->middleware('auth:sanctum');

});
});
// ─────────────────────────────────────────────────────────────
// 🆓 FREEMIUM TRIAL — status check, tiers, premium upgrade (receipt)
// ─────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/trial/status', [\App\Http\Controllers\Api\TrialController::class, 'status']);
    Route::get('/trial/tiers', [\App\Http\Controllers\Api\TrialController::class, 'tiers']); // FIX: was 404 — route was missing
    Route::post('/trial/upgrade', [\App\Http\Controllers\Api\TrialController::class, 'upgrade']);
});

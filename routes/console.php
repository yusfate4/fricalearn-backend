<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\MonthlyReportController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// ─────────────────────────────────────────────────────────────
// LEGACY REPORTS — disabled to avoid duplicate parent emails.
// The new reports:weekly-feedback and reports:monthly-performance
// below replace these. Re-enable ONLY if these old ones cover
// language-course data you still want sent separately.
// ─────────────────────────────────────────────────────────────

// Schedule::command('digest:weekly-parent')->weeklyOn(0, '20:00');

// Schedule::call(function () {
//     $students = DB::table('users')->where('role', 'student')->where('is_active', 1)->get();
//     $reportController = new MonthlyReportController();
//     foreach ($students as $student) {
//         $reportController->emailMonthlyReport($student->id);
//     }
// })->monthlyOn(1, '08:00');

// --- 🔔 OUTSTANDING TASK REMINDER ---
// Runs every Wednesday at 10:00 AM
Schedule::command('reminders:outstanding-tasks')->wednesdays()->at('10:00');

// 🧠 AI CONTENT — fills lessons Oak has no transcript for
// (remove once all lessons have content)
Schedule::command('oak:generate-content --limit=10')->everyTenMinutes();

// 📝 AI QUIZZES — for lessons with content but no quiz
// (remove once all lessons have quizzes)
Schedule::command('oak:generate-quizzes --limit=10')->everyTenMinutes();

// 📊 MONTHLY PERFORMANCE REPORT → parents (1st of month, 9:00 AM)
Schedule::command('reports:monthly-performance')->monthlyOn(1, '09:00');

// 📬 WEEKLY FEEDBACK → parents (Sundays, 7:00 PM)
Schedule::command('reports:weekly-feedback')->weeklyOn(0, '19:00');

// ⏰ INACTIVITY REMINDER — students idle 14+ days (daily check, 10:00 AM)
Schedule::command('reminders:inactive-students')->dailyAt('10:00');

// 💜 TRIAL FEEDBACK CHECK-INS → parents on trial (Tuesdays 10 AM + Fridays 5 PM)
Schedule::command('reports:trial-feedback')->weeklyOn(2, '10:00');
Schedule::command('reports:trial-feedback')->weeklyOn(5, '17:00');


Schedule::call(function () {
    $unread = DB::table('messages')
        ->where('is_read', false)
        ->where('created_at', '>=', now()->subMinutes(30))
        ->count();
    
    if ($unread > 0) {
        // Send WhatsApp via Twilio or just log for now
        Log::info("UNREAD CHAT ALERT: {$unread} new messages in last 30 mins");
    }
})->everyThirtyMinutes();
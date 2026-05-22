<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\MonthlyReportController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// --- 📅 WEEKLY PARENT DIGEST ---
// Runs every Sunday at 8:00 PM
Schedule::command('digest:weekly-parent')->weeklyOn(0, '20:00');

// --- 🔔 OUTSTANDING TASK REMINDER ---
// Runs every Wednesday at 10:00 AM
Schedule::command('reminders:outstanding-tasks')->wednesdays()->at('10:00');

// 🚀 AUTOMATED MONTHLY REPORTS
Schedule::call(function () {
    $students = DB::table('users')->where('role', 'student')->where('is_active', 1)->get();
    $reportController = new MonthlyReportController();
    
    foreach ($students as $student) {
        // Send email (Controller handles catching empty data)
        $reportController->emailMonthlyReport($student->id);
    }
})->monthlyOn(1, '08:00'); // Runs on the 1st of every month at 8:00 AM
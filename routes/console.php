<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// --- 📅 WEEKLY PARENT DIGEST ---
// Runs every Sunday at 8:00 PM
Schedule::command('digest:weekly-parent')->weeklyOn(0, '20:00');

// --- 🔔 OUTSTANDING TASK REMINDER ---
// Runs every Wednesday at 10:00 AM
Schedule::command('reminders:outstanding-tasks')->wednesdays()->at('10:00');
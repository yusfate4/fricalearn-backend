<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class RemindInactiveStudents extends Command
{
    protected $signature   = 'reminders:inactive-students';
    protected $description = 'Email parents when a student has been inactive for 14+ days';

    public function handle(): int
    {
        $cutoff = now()->subDays(14);
        $sent   = 0;

        $pairs = DB::table('parent_child as pc')
            ->join('users as p', 'p.id', '=', 'pc.parent_id')
            ->join('users as c', 'c.id', '=', 'pc.child_id')
            ->where('c.role', 'student')
            ->select('p.email as parent_email', 'p.name as parent_name',
                     'c.id as child_id', 'c.name as child_name',
                     'c.last_inactivity_reminder_at', 'c.created_at as child_created_at')
            ->get();

        foreach ($pairs as $pair) {
            // Skip brand-new accounts (give them 14 days from signup)
            if ($pair->child_created_at && $pair->child_created_at > $cutoff) continue;

            // Don't remind more than once per 14 days
            if ($pair->last_inactivity_reminder_at && $pair->last_inactivity_reminder_at > $cutoff) continue;

            // Last activity = most recent of quiz submission or lesson progress
            $lastQuiz = DB::table('quiz_performance')
                ->where('student_id', $pair->child_id)->max('completed_at');
            $lastProgress = DB::table('user_external_lesson_progress')
                ->where('user_id', $pair->child_id)->max('updated_at');

            $lastActivity = max($lastQuiz ?? '1970-01-01', $lastProgress ?? '1970-01-01');

            if ($lastActivity >= $cutoff) continue; // active — skip

            $daysInactive = (int) now()->diffInDays($lastActivity);
            $daysText     = $daysInactive > 365 ? "a while" : "{$daysInactive} days";

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#0E1C0E;padding:24px;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#F4B400;'>Learn</span></h1>
              </div>
              <div style='background:#fff;border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 16px 16px;'>
                <p>Dear " . e($pair->parent_name) . ",</p>
                <h2 style='color:#b45309;font-size:18px;'>We miss " . e($pair->child_name) . "! 👋</h2>
                <p style='line-height:1.6;'>It's been <strong>{$daysText}</strong> since " . e($pair->child_name) . " last learned on FricaLearn.
                Regular practice — even 15 minutes a few times a week — makes a big difference to progress.</p>
                <p style='line-height:1.6;'>Their lessons, quizzes, and AI Tutor are all waiting exactly where they left off.</p>
                <p style='margin-top:20px;'><a href='https://fricalearn.com/login' style='background:#2D5A27;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>Continue Learning</a></p>
                <p style='color:#999;font-size:12px;margin-top:24px;'>Need help? Reply to this email or WhatsApp us on +234 817 448 5504. · hello@fricalearn.com</p>
              </div>
            </div>";

            try {
                Mail::html($html, function ($message) use ($pair) {
                    $message->to($pair->parent_email)
                        ->subject("👋 {$pair->child_name} hasn't learned in 2 weeks — let's get back on track!");
                });

                DB::table('users')->where('id', $pair->child_id)
                    ->update(['last_inactivity_reminder_at' => now()]);

                $sent++;
                $this->line("Reminded: {$pair->parent_email} ({$pair->child_name}, inactive {$daysText})");
            } catch (\Exception $e) {
                Log::error("Inactivity reminder failed for {$pair->parent_email}: " . $e->getMessage());
            }
        }

        $this->info("Inactivity reminders sent: {$sent}");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWeeklyFeedback extends Command
{
    protected $signature   = 'reports:weekly-feedback';
    protected $description = 'Email each parent a short weekly feedback summary of their child\'s learning';

    public function handle(): int
    {
        $weekStart = now()->subDays(7);

        $pairs = DB::table('parent_child as pc')
            ->join('users as p', 'p.id', '=', 'pc.parent_id')
            ->join('users as c', 'c.id', '=', 'pc.child_id')
            ->select('p.email as parent_email', 'p.name as parent_name',
                     'c.id as child_id', 'c.name as child_name')
            ->get();

        $sent = 0;

        foreach ($pairs as $pair) {
            $quizzes = DB::table('quiz_performance')
                ->where('student_id', $pair->child_id)
                ->where('completed_at', '>=', $weekStart)
                ->get();

            $lessonsCompleted = DB::table('user_external_lesson_progress')
                ->where('user_id', $pair->child_id)
                ->where('status', 'completed')
                ->where('completed_at', '>=', $weekStart)
                ->count();

            $avgScore = $quizzes->isNotEmpty() ? round($quizzes->avg('score')) : null;

            // Feedback tone based on the week's activity
            if ($lessonsCompleted === 0 && $quizzes->isEmpty()) {
                $headline = "No learning activity this week";
                $body     = e($pair->child_name) . " didn't complete any lessons this week. A little encouragement goes a long way — even one lesson keeps the momentum going! 🌱";
                $tone     = '#b45309';
            } elseif ($avgScore !== null && $avgScore >= 80) {
                $headline = "A fantastic week of learning! 🌟";
                $body     = e($pair->child_name) . " completed <strong>{$lessonsCompleted} lesson(s)</strong> with an average quiz score of <strong>{$avgScore}%</strong>. Outstanding effort — please pass on our congratulations!";
                $tone     = '#2D5A27';
            } else {
                $headline = "Steady progress this week 👍";
                $body     = e($pair->child_name) . " completed <strong>{$lessonsCompleted} lesson(s)</strong>" .
                    ($avgScore !== null ? " with an average quiz score of <strong>{$avgScore}%</strong>" : "") .
                    ". Consistent practice is exactly how strong learners are built.";
                $tone     = '#2D5A27';
            }

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#0E1C0E;padding:24px;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#F4B400;'>Learn</span> — Weekly Feedback</h1>
              </div>
              <div style='background:#fff;border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 16px 16px;'>
                <p>Dear " . e($pair->parent_name) . ",</p>
                <h2 style='color:{$tone};font-size:18px;margin:12px 0;'>{$headline}</h2>
                <p style='line-height:1.6;'>{$body}</p>
                <p style='margin-top:20px;'><a href='https://fricalearn.com/login' style='background:#2D5A27;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>Open Parent Portal</a></p>
                <p style='color:#999;font-size:12px;margin-top:24px;'>You receive this weekly summary as a FricaLearn parent. · hello@fricalearn.com</p>
              </div>
            </div>";

            try {
                Mail::html($html, function ($message) use ($pair) {
                    $message->to($pair->parent_email)
                        ->subject("📚 {$pair->child_name}'s week at FricaLearn");
                });
                $sent++;
            } catch (\Exception $e) {
                Log::error("Weekly feedback failed for {$pair->parent_email}: " . $e->getMessage());
            }
        }

        $this->info("Weekly feedback emails sent: {$sent}");
        return self::SUCCESS;
    }
}

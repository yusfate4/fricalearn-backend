<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class RemindInactiveStudents extends Command
{
    protected $signature   = 'reminders:inactive-students';
    protected $description = 'Email parents ONE summary when their student(s) have been inactive for 14+ days';

    public function handle(): int
    {
        $cutoff = now()->subDays(14);

        $pairs = DB::table('parent_child as pc')
            ->join('users as p', 'p.id', '=', 'pc.parent_id')
            ->join('users as c', 'c.id', '=', 'pc.child_id')
            ->where('c.role', 'student')
            ->select('p.email as parent_email', 'p.name as parent_name',
                     'c.id as child_id', 'c.name as child_name',
                     'c.last_inactivity_reminder_at', 'c.created_at as child_created_at')
            ->distinct()
            ->get()
            ->unique('child_id'); // guard against duplicate parent_child rows

        // ── Collect inactive children, grouped by parent email ──
        $byParent = [];

        foreach ($pairs as $pair) {
            // Grace period: brand-new accounts get 14 days from signup
            if ($pair->child_created_at && Carbon::parse($pair->child_created_at)->gt($cutoff)) continue;

            // Max one reminder per child per 14 days
            if ($pair->last_inactivity_reminder_at
                && Carbon::parse($pair->last_inactivity_reminder_at)->gt($cutoff)) continue;

            $lastQuiz = DB::table('quiz_performance')
                ->where('student_id', $pair->child_id)->max('completed_at');
            $lastProgress = DB::table('user_external_lesson_progress')
                ->where('user_id', $pair->child_id)->max('updated_at');

            $lastActivity = collect([$lastQuiz, $lastProgress])->filter()->max(); // null if never active

            if ($lastActivity && Carbon::parse($lastActivity)->gte($cutoff)) continue; // active — skip

            // Human-friendly inactivity text (Carbon 3: use absolute diff)
            if ($lastActivity) {
                $days = (int) Carbon::parse($lastActivity)->diffInDays(now(), true);
                $inactiveText = $days > 60 ? "over 2 months" : "{$days} days";
            } else {
                $inactiveText = null; // never started
            }

            $byParent[$pair->parent_email]['parent_name'] = $pair->parent_name;
            $byParent[$pair->parent_email]['children'][] = [
                'id'   => $pair->child_id,
                'name' => $pair->child_name,
                'text' => $inactiveText,
            ];
        }

        // ── Send ONE email per parent ────────────────────────────
        $sent = 0;

        foreach ($byParent as $email => $data) {
            $children = $data['children'];

            $childRows = implode('', array_map(function ($c) {
                $status = $c['text']
                    ? "hasn't learned in <strong>{$c['text']}</strong>"
                    : "<strong>hasn't started learning yet</strong>";
                return "<li style='margin-bottom:8px;line-height:1.5;'>" . e($c['name']) . " — {$status}</li>";
            }, $children));

            $plural   = count($children) > 1;
            $whoText  = $plural ? "your children" : e($children[0]['name']);
            $subjName = $plural ? count($children) . " of your children" : $children[0]['name'];

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#0E1C0E;padding:24px;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#F4B400;'>Learn</span></h1>
              </div>
              <div style='background:#fff;border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 16px 16px;'>
                <p>Dear " . e($data['parent_name']) . ",</p>
                <h2 style='color:#b45309;font-size:18px;'>We miss {$whoText}! 👋</h2>
                <ul style='padding-left:20px;'>{$childRows}</ul>
                <p style='line-height:1.6;'>Regular practice — even 15 minutes a few times a week — makes a big difference.
                Lessons, quizzes, and the AI Tutor are waiting exactly where they left off.</p>
                <p style='margin-top:20px;'><a href='https://fricalearn.com/login' style='background:#2D5A27;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>Continue Learning</a></p>
                <p style='color:#999;font-size:12px;margin-top:24px;'>Need help? Reply to this email or WhatsApp us on +234 817 448 5504. · hello@fricalearn.com</p>
              </div>
            </div>";

            try {
                Mail::html($html, function ($message) use ($email, $subjName) {
                    $message->to($email)->subject("👋 {$subjName} — let's get back to learning on FricaLearn!");
                });

                DB::table('users')
                    ->whereIn('id', array_column($children, 'id'))
                    ->update(['last_inactivity_reminder_at' => now()]);

                $sent++;
                $names = implode(', ', array_column($children, 'name'));
                $this->line("Reminded {$email}: {$names}");
            } catch (\Exception $e) {
                Log::error("Inactivity reminder failed for {$email}: " . $e->getMessage());
            }
        }

        $this->info("Inactivity reminder emails sent: {$sent} (one per parent)");
        return self::SUCCESS;
    }
}

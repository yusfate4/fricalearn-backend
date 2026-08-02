<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTrialFeedbackCheckins extends Command
{
    protected $signature   = 'reports:trial-feedback';
    protected $description = 'Twice-weekly check-in email to parents of students on free trial — is your child enjoying it, any feedback?';

    public function handle(): int
    {
        // Parents of students CURRENTLY on trial
        $pairs = DB::table('parent_child as pc')
            ->join('users as p', 'p.id', '=', 'pc.parent_id')
            ->join('users as c', 'c.id', '=', 'pc.child_id')
            ->where('c.role', 'student')
            ->where('c.is_premium', 0)
            ->whereNotNull('c.trial_ends_at')
            ->where('c.trial_ends_at', '>', now())
            ->select('p.email as parent_email', 'p.name as parent_name',
                     'c.id as child_id', 'c.name as child_name')
            ->distinct()
            ->get()
            ->unique('child_id');

        // Group by parent — one email even with several trialing children
        $byParent = [];
        foreach ($pairs as $pair) {
            // Light activity stat for a personal touch (last 4 days ≈ since previous check-in)
            $recentLessons = DB::table('user_external_lesson_progress')
                ->where('user_id', $pair->child_id)
                ->where('updated_at', '>=', now()->subDays(4))
                ->count();

            $byParent[$pair->parent_email]['parent_name'] = $pair->parent_name;
            $byParent[$pair->parent_email]['children'][] = [
                'name'    => $pair->child_name,
                'lessons' => $recentLessons,
            ];
        }

        $sent = 0;

        foreach ($byParent as $email => $data) {
            $children  = $data['children'];
            $plural    = count($children) > 1;
            $firstName = $children[0]['name'];
            $who       = $plural ? 'your children' : $firstName;

            $activityLines = implode('', array_map(function ($c) {
                return $c['lessons'] > 0
                    ? "<li style='margin-bottom:6px;'>" . e($c['name']) . " worked on <strong>{$c['lessons']} lesson" . ($c['lessons'] !== 1 ? 's' : '') . "</strong> in the last few days 🎉</li>"
                    : "<li style='margin-bottom:6px;'>" . e($c['name']) . " hasn't opened a lesson in the last few days — a nudge from you works wonders! 🌱</li>";
            }, $children));

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#2A1650;padding:24px;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#FFFF00;'>Learn</span></h1>
              </div>
              <div style='background:#fff;border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 16px 16px;'>
                <p>Dear " . e($data['parent_name']) . ",</p>
                <h2 style='color:#3F2171;font-size:18px;'>How is {$who} finding FricaLearn? 💜</h2>
                <p style='line-height:1.6;'>You're partway through your free trial and we'd genuinely love to know how it's going:</p>
                <ul style='padding-left:20px;line-height:1.6;'>{$activityLines}</ul>
                <div style='background:#f3effa;border-radius:12px;padding:16px;margin:16px 0;'>
                  <p style='margin:0 0 8px;font-weight:bold;color:#3F2171;'>We'd love your feedback:</p>
                  <p style='margin:0;font-size:14px;line-height:1.6;'>
                    Is " . ($plural ? "each child" : e($firstName)) . " enjoying the lessons? Is anything confusing or missing?
                    Simply <strong>reply to this email</strong> or message us on
                    <a href='https://wa.me/2348174485504' style='color:#3F2171;font-weight:bold;'>WhatsApp</a> —
                    a real person reads every reply, and your feedback shapes what we build next.
                  </p>
                </div>
                <p style='margin-top:20px;'><a href='https://fricalearn.com/login' style='background:#3F2171;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>Open Parent Portal</a></p>
                <p style='color:#999;font-size:12px;margin-top:24px;'>You receive these check-ins during your free trial. · hello@fricalearn.com</p>
              </div>
            </div>";

            try {
                Mail::html($html, function ($message) use ($email, $who) {
                    $message->to($email)
                        ->replyTo('hello@fricalearn.com')
                        ->subject("💜 How is {$who} enjoying FricaLearn? We'd love your feedback");
                });
                $sent++;
            } catch (\Exception $e) {
                Log::error("Trial feedback check-in failed for {$email}: " . $e->getMessage());
            }
        }

        $this->info("Trial feedback check-ins sent: {$sent}");
        return self::SUCCESS;
    }
}

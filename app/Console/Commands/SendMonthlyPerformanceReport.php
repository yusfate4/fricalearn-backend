<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendMonthlyPerformanceReport extends Command
{
    protected $signature   = 'reports:monthly-performance';
    protected $description = 'Email each parent a monthly overview of their child\'s quiz & lesson performance';

    public function handle(): int
    {
        $monthStart = now()->subMonth()->startOfMonth();
        $monthEnd   = now()->subMonth()->endOfMonth();
        $monthName  = $monthStart->format('F Y');

        $pairs = DB::table('parent_child as pc')
            ->join('users as p', 'p.id', '=', 'pc.parent_id')
            ->join('users as c', 'c.id', '=', 'pc.child_id')
            ->select('p.email as parent_email', 'p.name as parent_name',
                     'c.id as child_id', 'c.name as child_name')
            ->get();

        $sent = 0;

        foreach ($pairs as $pair) {
            // ── Gather the month's stats ─────────────────────────
            $quizzes = DB::table('quiz_performance')
                ->where('student_id', $pair->child_id)
                ->whereBetween('completed_at', [$monthStart, $monthEnd])
                ->get();

            $lessonsCompleted = DB::table('user_external_lesson_progress')
                ->where('user_id', $pair->child_id)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$monthStart, $monthEnd])
                ->count();

            $topicsCompleted = DB::table('topic_evaluations')
                ->where('student_id', $pair->child_id)
                ->whereBetween('completed_at', [$monthStart, $monthEnd])
                ->get();

            if ($quizzes->isEmpty() && $lessonsCompleted === 0) continue; // covered by inactivity flow

            $avgScore  = $quizzes->isNotEmpty() ? round($quizzes->avg('score')) : 0;
            $passRate  = $quizzes->isNotEmpty()
                ? round($quizzes->where('passed', 1)->count() / $quizzes->count() * 100) : 0;

            // Weak areas: topics of failed quizzes this month
            $weakTopics = DB::table('quiz_performance as qp')
                ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                ->where('qp.student_id', $pair->child_id)
                ->whereBetween('qp.completed_at', [$monthStart, $monthEnd])
                ->where('qp.passed', 0)
                ->select('t.title', DB::raw('COUNT(*) as fails'))
                ->groupBy('t.title')->orderByDesc('fails')->limit(3)->pluck('t.title')->toArray();

            $topicRows = $topicsCompleted->map(fn($t) =>
                "<tr><td style='padding:8px;border-bottom:1px solid #eee;'>" .
                e(DB::table('external_topics')->where('id', $t->topic_id)->value('title')) .
                "</td><td style='padding:8px;border-bottom:1px solid #eee;text-align:center;font-weight:bold;color:#2D5A27;'>{$t->average_score}%</td>" .
                "<td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>{$t->grade_label}</td></tr>"
            )->implode('');

            $weakHtml = !empty($weakTopics)
                ? "<p style='margin:16px 0 4px;font-weight:bold;color:#b45309;'>Areas needing attention:</p><ul>" .
                  implode('', array_map(fn($w) => "<li>" . e($w) . "</li>", $weakTopics)) . "</ul>" .
                  "<p style='font-size:13px;color:#666;'>A tutor will focus on these areas as part of our weak-area support.</p>"
                : "<p style='color:#2D5A27;font-weight:bold;'>No weak areas flagged this month — fantastic work! 🎉</p>";

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#0E1C0E;padding:28px;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:22px;'>Frica<span style='color:#F4B400;'>Learn</span> — Monthly Report</h1>
                <p style='color:#ffffff99;margin:6px 0 0;'>{$monthName} · " . e($pair->child_name) . "</p>
              </div>
              <div style='background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;'>
                <p>Dear " . e($pair->parent_name) . ",</p>
                <p>Here is <strong>" . e($pair->child_name) . "'s</strong> learning summary for {$monthName}:</p>
                <table width='100%' style='margin:16px 0;'>
                  <tr>
                    <td style='background:#f0f7ef;border-radius:12px;padding:16px;text-align:center;'>
                      <div style='font-size:28px;font-weight:bold;color:#2D5A27;'>{$lessonsCompleted}</div>
                      <div style='font-size:11px;color:#666;text-transform:uppercase;'>Lessons Completed</div>
                    </td>
                    <td style='width:10px;'></td>
                    <td style='background:#f0f7ef;border-radius:12px;padding:16px;text-align:center;'>
                      <div style='font-size:28px;font-weight:bold;color:#2D5A27;'>{$avgScore}%</div>
                      <div style='font-size:11px;color:#666;text-transform:uppercase;'>Average Quiz Score</div>
                    </td>
                    <td style='width:10px;'></td>
                    <td style='background:#f0f7ef;border-radius:12px;padding:16px;text-align:center;'>
                      <div style='font-size:28px;font-weight:bold;color:#2D5A27;'>{$passRate}%</div>
                      <div style='font-size:11px;color:#666;text-transform:uppercase;'>Quiz Pass Rate</div>
                    </td>
                  </tr>
                </table>" .
                ($topicRows ? "<p style='font-weight:bold;margin-bottom:6px;'>Topics completed this month:</p>
                <table width='100%' style='border-collapse:collapse;font-size:14px;'>
                  <tr style='background:#2D5A27;color:#fff;'><th style='padding:8px;text-align:left;'>Topic</th><th style='padding:8px;'>Score</th><th style='padding:8px;'>Grade</th></tr>
                  {$topicRows}
                </table>" : "") .
                "{$weakHtml}
                <p style='margin-top:24px;'>Log in to your parent portal to see full details.</p>
                <p style='color:#999;font-size:12px;'>FRICA SOLUTION LIMITED · hello@fricalearn.com</p>
              </div>
            </div>";

            try {
                Mail::html($html, function ($message) use ($pair, $monthName) {
                    $message->to($pair->parent_email)
                        ->subject("📊 {$pair->child_name}'s FricaLearn Report — {$monthName}");
                });
                $sent++;
                $this->line("Sent: {$pair->parent_email} ({$pair->child_name})");
            } catch (\Exception $e) {
                Log::error("Monthly report failed for {$pair->parent_email}: " . $e->getMessage());
            }
        }

        $this->info("Monthly performance reports sent: {$sent}");
        return self::SUCCESS;
    }
}

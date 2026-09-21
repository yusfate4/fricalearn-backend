<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendMonthlyPerformanceReport extends Command
{
    protected $signature   = 'reports:monthly-performance {--current : Use current month instead of last month} {--test-parent= : Run for one parent email only}';
    protected $description = 'Email each parent a detailed monthly report with strengths, weak areas, and recommendations';

    public function handle(): int
    {
        $base       = $this->option('current') ? now() : now()->subMonth();
        $monthStart = $base->copy()->startOfMonth();
        $monthEnd   = $base->copy()->endOfMonth();
        $monthName  = $monthStart->format('F Y');
        $testEmail  = $this->option('test-parent');

        $this->info("Running monthly report for: {$monthName}");

        $query = DB::table('parent_child as pc')
            ->join('users as p', 'p.id', '=', 'pc.parent_id')
            ->join('users as c', 'c.id', '=', 'pc.child_id')
            ->where('c.is_active', 1)
            ->select(
                'p.email as parent_email', 'p.name as parent_name',
                'c.id as child_id',        'c.name as child_name',
                'c.trial_ends_at',         'c.is_premium',
                'c.selected_courses'
            );

        if ($testEmail) {
            $query->where('p.email', $testEmail);
        }

        $pairs = $query->get();
        $sent  = 0;

        foreach ($pairs as $pair) {
            try {
                $this->line("Building report: {$pair->child_name}");

                // ── Core stats ─────────────────────────────────────────
                $quizzes = DB::table('quiz_performance')
                    ->where('student_id', $pair->child_id)
                    ->whereBetween('completed_at', [$monthStart, $monthEnd])
                    ->get();

                $lessonsCompleted = DB::table('user_external_lesson_progress')
                    ->where('user_id', $pair->child_id)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$monthStart, $monthEnd])
                    ->count();

                // Skip if truly inactive (inactivity reminders cover this)
                if ($quizzes->isEmpty() && $lessonsCompleted === 0) {
                    $this->line("  ⏭ Skipped (no activity)");
                    continue;
                }

                $avgScore  = $quizzes->isNotEmpty() ? round($quizzes->avg('score')) : 0;
                $passCount = $quizzes->where('passed', 1)->count();
                $totalQuiz = $quizzes->count();
                $passRate  = $totalQuiz > 0 ? round($passCount / $totalQuiz * 100) : 0;
                $totalXP   = DB::table('student_profiles')->where('user_id', $pair->child_id)->value('total_points') ?? 0;
                $level     = DB::table('student_profiles')->where('user_id', $pair->child_id)->value('current_level') ?? 'Beginner';

                // ── Strong topics (avg ≥ 75%) ─────────────────────────
                $strongTopics = DB::table('quiz_performance as qp')
                    ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                    ->join('external_subjects as s', 's.id', '=', 't.subject_id')
                    ->where('qp.student_id', $pair->child_id)
                    ->whereBetween('qp.completed_at', [$monthStart, $monthEnd])
                    ->where('qp.passed', 1)
                    ->select(
                        't.title',
                        's.name as subject',
                        DB::raw('ROUND(AVG(qp.score)) as avg_score'),
                        DB::raw('COUNT(*) as attempts')
                    )
                    ->groupBy('t.id', 't.title', 's.id', 's.name')
                    ->having('avg_score', '>=', 75)
                    ->orderByDesc('avg_score')
                    ->limit(5)
                    ->get();

                // ── Weak topics (failed quizzes or avg < 60%) ─────────
                $weakTopics = DB::table('quiz_performance as qp')
                    ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                    ->join('external_subjects as s', 's.id', '=', 't.subject_id')
                    ->where('qp.student_id', $pair->child_id)
                    ->whereBetween('qp.completed_at', [$monthStart, $monthEnd])
                    ->select(
                        't.title',
                        's.name as subject',
                        DB::raw('ROUND(AVG(qp.score)) as avg_score'),
                        DB::raw('SUM(CASE WHEN qp.passed = 0 THEN 1 ELSE 0 END) as fails'),
                        DB::raw('COUNT(*) as attempts')
                    )
                    ->groupBy('t.id', 't.title', 's.id', 's.name')
                    ->having('avg_score', '<', 60)
                    ->orderBy('avg_score')
                    ->limit(5)
                    ->get();

                // ── Topic evaluations ─────────────────────────────────
                $topicEvals = DB::table('topic_evaluations as te')
                    ->join('external_topics as t', 't.id', '=', 'te.topic_id')
                    ->where('te.student_id', $pair->child_id)
                    ->whereBetween('te.completed_at', [$monthStart, $monthEnd])
                    ->select('t.title', 'te.average_score', 'te.grade_label')
                    ->orderByDesc('te.average_score')
                    ->limit(5)
                    ->get();

                // ── Week-by-week activity breakdown ───────────────────
                $weeklyBreakdown = [];
                for ($i = 3; $i >= 0; $i--) {
                    $ws = $monthStart->copy()->addDays($i * 7);
                    $we = $ws->copy()->addDays(6)->min($monthEnd);
                    $wLessons = DB::table('user_external_lesson_progress')
                        ->where('user_id', $pair->child_id)
                        ->where('status', 'completed')
                        ->whereBetween('completed_at', [$ws, $we])
                        ->count();
                    $wQuizzes = DB::table('quiz_performance')
                        ->where('student_id', $pair->child_id)
                        ->whereBetween('completed_at', [$ws, $we])
                        ->get();
                    $weeklyBreakdown[] = [
                        'label'   => 'Wk ' . ($i === 0 ? '1' : ($i === 1 ? '2' : ($i === 2 ? '3' : '4'))),
                        'lessons' => $wLessons,
                        'score'   => $wQuizzes->isNotEmpty() ? round($wQuizzes->avg('score')) : null,
                    ];
                }
                $weeklyBreakdown = array_reverse($weeklyBreakdown);

                // ── Generate recommendations ──────────────────────────
                $recommendations = $this->generateRecommendations(
                    $avgScore, $passRate, $lessonsCompleted, $weakTopics, $strongTopics, $weeklyBreakdown
                );

                // ── Overall grade ──────────────────────────────────────
                [$grade, $gradeColor, $gradeMsg] = $this->calculateGrade($avgScore, $passRate, $lessonsCompleted);

                // ── Build email HTML ───────────────────────────────────
                $html = $this->buildMonthlyHtml(
                    $pair, $monthName, $grade, $gradeColor, $gradeMsg,
                    $lessonsCompleted, $avgScore, $passRate, $totalXP, $level,
                    $strongTopics, $weakTopics, $topicEvals,
                    $weeklyBreakdown, $recommendations
                );

                Mail::html($html, function ($m) use ($pair, $monthName) {
                    $m->to($pair->parent_email)
                      ->subject("📈 Monthly Report: " . e($pair->child_name) . " — {$monthName}");
                });

                $this->line("  ✅ Sent to {$pair->parent_email}");
                $sent++;

            } catch (\Exception $e) {
                Log::error("Monthly report failed for child {$pair->child_id}: " . $e->getMessage());
                $this->warn("  ❌ Failed: " . $e->getMessage());
            }
        }

        $this->info("Monthly reports sent: {$sent}/{$pairs->count()}");
        return self::SUCCESS;
    }

    private function calculateGrade(int $avgScore, int $passRate, int $lessons): array
    {
        if ($avgScore >= 80 && $passRate >= 75 && $lessons >= 10) return ['A', '#1A7A4A', 'Excellent — your child is thriving'];
        if ($avgScore >= 70 && $passRate >= 60 && $lessons >= 6)  return ['B', '#2563EB', 'Good — solid and consistent progress'];
        if ($avgScore >= 60 || $lessons >= 4)                      return ['C', '#7C3AED', 'Steady — building the right habits'];
        if ($lessons > 0)                                           return ['D', '#B45309', 'Needs support — more practice required'];
        return ['—', '#9CA3AF', 'Insufficient data this month'];
    }

    private function generateRecommendations(int $avg, int $passRate, int $lessons, $weak, $strong, array $weekly): array
    {
        $recs = [];

        // Consistency recommendation
        $activeDays = collect($weekly)->filter(function($w) { return $w['lessons'] > 0; })->count();
        if ($activeDays <= 1) {
            $recs[] = [
                'type'  => 'habit',
                'title' => 'Build a daily learning habit',
                'body'  => 'Your child only had active learning days in ' . $activeDays . ' of the 4 weeks. Even 15 minutes per day produces better results than longer, infrequent sessions.',
                'icon'  => '🗓',
            ];
        } elseif ($activeDays === 4) {
            $recs[] = [
                'type'  => 'habit',
                'title' => 'Outstanding consistency!',
                'body'  => 'Your child was active every week this month. This kind of regularity is the single biggest predictor of academic progress.',
                'icon'  => '🏆',
            ];
        }

        // Weak area recommendation
        if ($weak->isNotEmpty()) {
            $topic = e($weak->first()->title);
            $score = $weak->first()->avg_score;
            $recs[] = [
                'type'  => 'focus',
                'title' => "Focus area: {$topic}",
                'body'  => "Average score of {$score}% — this topic needs more attention next month. Encourage your child to re-read the lesson before attempting the quiz, and to use the AI Tutor if they get stuck.",
                'icon'  => '📌',
            ];
        }

        // Low quiz score recommendation
        if ($avg > 0 && $avg < 65) {
            $recs[] = [
                'type'  => 'score',
                'title' => 'Read before quizzing',
                'body'  => "An average score of {$avg}% suggests lessons aren't being read fully before the quiz. Encourage your child to read each lesson from start to finish before clicking 'Take Quiz'.",
                'icon'  => '📖',
            ];
        }

        // Build on strength
        if ($strong->isNotEmpty()) {
            $strongTopic = e($strong->first()->title);
            $recs[] = [
                'type'  => 'strength',
                'title' => "Build on this month's strengths",
                'body'  => "Your child excelled at <strong>{$strongTopic}</strong>. The next topic in the sequence builds directly on these skills — encourage them to move forward while confidence is high.",
                'icon'  => '⭐',
            ];
        }

        // Volume recommendation
        if ($lessons < 4) {
            $recs[] = [
                'type'  => 'volume',
                'title' => 'Aim for more lessons next month',
                'body'  => "Only {$lessons} lesson(s) completed this month. Even 2 lessons per week — 8 per month — is enough to see meaningful progression through the curriculum.",
                'icon'  => '🎯',
            ];
        } elseif ($lessons >= 15) {
            $recs[] = [
                'type'  => 'volume',
                'title' => "Great volume — {$lessons} lessons completed!",
                'body'  => "Your child is moving through the curriculum at an impressive pace. Make sure they're retaining what they learn by checking quiz scores alongside lesson counts.",
                'icon'  => '🚀',
            ];
        }

        return array_slice($recs, 0, 4);
    }

    private function buildMonthlyHtml($pair, string $month, string $grade, string $gradeColor, string $gradeMsg,
        int $lessons, int $avg, int $passRate, int $xp, string $level,
        $strong, $weak, $evals, array $weekly, array $recs): string
    {
        $name = e($pair->child_name);

        // Stats boxes
        $stats = "
        <table width='100%' style='border-collapse:separate;border-spacing:6px;margin:20px 0;'>
          <tr>
            <td style='background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;'>
              <div style='font-size:26px;font-weight:900;color:#3F2171;'>{$lessons}</div>
              <div style='font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;'>Lessons</div>
            </td>
            <td style='background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;'>
              <div style='font-size:26px;font-weight:900;color:#3F2171;'>{$avg}%</div>
              <div style='font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;'>Avg Score</div>
            </td>
            <td style='background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;'>
              <div style='font-size:26px;font-weight:900;color:#3F2171;'>{$passRate}%</div>
              <div style='font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;'>Pass Rate</div>
            </td>
            <td style='background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;'>
              <div style='font-size:26px;font-weight:900;color:#3F2171;'>{$xp}</div>
              <div style='font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;'>Total XP</div>
            </td>
          </tr>
        </table>";

        // Weekly breakdown bar chart (text-based)
        $weekRows = implode('', array_map(function ($w) {
            $bar = str_repeat('█', max(0, (int)($w['lessons'] * 5)));
            $score = $w['score'] !== null ? " · {$w['score']}% avg" : '';
            return "<tr>
                <td style='font-size:11px;font-weight:700;color:#555;padding:4px 8px 4px 0;width:40px;'>{$w['label']}</td>
                <td style='font-size:11px;color:#3F2171;padding:4px;'>{$bar}</td>
                <td style='font-size:11px;color:#888;padding:4px;'>{$w['lessons']} lesson(s){$score}</td>
            </tr>";
        }, $weekly));

        $weeklyHtml = "
        <div style='background:#f9f7ff;border-radius:12px;padding:16px;margin:16px 0;'>
          <p style='font-weight:900;color:#3F2171;margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:1px;'>📅 Week-by-week activity</p>
          <table style='width:100%;'>{$weekRows}</table>
        </div>";

        // Strong topics
        $strongHtml = '';
        if ($strong->isNotEmpty()) {
            $rows = $strong->map(function($t) { return 
                "<tr>
                  <td style='padding:8px 0;font-size:13px;color:#333;border-bottom:1px solid #e8f5e9;'>" . e($t->title) . "<br><span style='font-size:10px;color:#888;'>" . e($t->subject) . "</span></td>
                  <td style='padding:8px 0;text-align:right;font-weight:900;color:#1A7A4A;font-size:14px;border-bottom:1px solid #e8f5e9;'>{$t->avg_score}%</td>
                </tr>"
            )->implode('');
            $strongHtml = "
            <div style='background:#e8f5e9;border-radius:12px;padding:16px;margin:16px 0;'>
              <p style='font-weight:900;color:#1A7A4A;margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:1px;'>⭐ Areas of Strength</p>
              <table style='width:100%;'>{$rows}</table>
            </div>";
        }

        // Weak topics
        $weakHtml = '';
        if ($weak->isNotEmpty()) {
            $rows = $weak->map(function($t) { return 
                "<tr>
                  <td style='padding:8px 0;font-size:13px;color:#333;border-bottom:1px solid #fff3cd;'>" . e($t->title) . "<br><span style='font-size:10px;color:#888;'>" . e($t->subject) . "</span></td>
                  <td style='padding:8px 0;text-align:right;font-weight:900;color:#b45309;font-size:14px;border-bottom:1px solid #fff3cd;'>{$t->avg_score}%</td>
                </tr>"
            )->implode('');
            $weakHtml = "
            <div style='background:#fff8e1;border-radius:12px;padding:16px;margin:16px 0;'>
              <p style='font-weight:900;color:#b45309;margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:1px;'>📌 Areas to Improve</p>
              <table style='width:100%;'>{$rows}</table>
            </div>";
        }

        // Recommendations
        $recsHtml = '';
        if (!empty($recs)) {
            $items = implode('', array_map(function($r) { return
                "<div style='border-left:3px solid #3F2171;padding:10px 14px;margin:10px 0;background:#faf9ff;border-radius:0 8px 8px 0;'>
                  <p style='font-weight:900;color:#3F2171;margin:0 0 4px;font-size:13px;'>{$r['icon']} {$r['title']}</p>
                  <p style='color:#555;font-size:13px;line-height:1.6;margin:0;'>{$r['body']}</p>
                </div>",
                $recs; },
            $recs
            ));
            $recsHtml = "
            <div style='margin:20px 0;'>
              <p style='font-weight:900;color:#2A1650;margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:1px;'>📋 Recommendations for Next Month</p>
              {$items}
            </div>";
        }

        return "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>

          <!-- Header -->
          <div style='background:#2A1650;padding:28px;border-radius:16px 16px 0 0;'>
            <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#FFFF00;'>Learn</span></h1>
            <p style='color:#ffffff80;margin:6px 0 0;font-size:13px;'>Monthly Progress Report · {$month}</p>
          </div>

          <div style='background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;'>
            <p style='font-size:15px;color:#333;'>Dear " . e($pair->parent_name) . ",</p>
            <p style='font-size:14px;line-height:1.7;color:#555;'>
              Here is <strong>{$name}'s</strong> full learning report for {$month}.
              This report covers lessons completed, quiz performance, areas of strength, areas to improve, and our recommendations for next month.
            </p>

            <!-- Grade badge -->
            <div style='background:{$gradeColor};border-radius:16px;padding:20px;margin:20px 0;display:flex;align-items:center;gap:16px;'>
              <div style='background:rgba(255,255,255,0.2);border-radius:12px;padding:8px 16px;text-align:center;'>
                <div style='font-size:36px;font-weight:900;color:#fff;line-height:1;'>{$grade}</div>
                <div style='font-size:9px;color:rgba(255,255,255,0.8);text-transform:uppercase;letter-spacing:1px;font-weight:700;'>Grade</div>
              </div>
              <div>
                <p style='color:#fff;font-weight:900;margin:0;font-size:16px;'>{$gradeMsg}</p>
                <p style='color:rgba(255,255,255,0.75);margin:4px 0 0;font-size:12px;'>Rank: {$level} · {$xp} total XP</p>
              </div>
            </div>

            {$stats}
            {$weeklyHtml}
            {$strongHtml}
            {$weakHtml}
            {$recsHtml}

            <div style='margin-top:28px;padding-top:20px;border-top:1px solid #eee;'>
              <a href='https://fricalearn.com/parent/dashboard'
                 style='background:#3F2171;color:#fff;padding:14px 28px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block;margin-right:12px;'>
                Open Dashboard
              </a>
              <a href='https://fricalearn.com/login'
                 style='background:#f3effa;color:#3F2171;padding:14px 28px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block;'>
                {$name}'s Portal
              </a>
            </div>

            <p style='color:#aaa;font-size:11px;margin-top:24px;line-height:1.6;'>
              FRICA SOLUTION LIMITED · hello@fricalearn.com · WhatsApp +234 817 448 5504<br>
              Monthly reports are sent on the 1st of each month for the previous month's activity.<br>
              Curriculum content © Oak National Academy.
            </p>
          </div>
        </div>";
    }
}

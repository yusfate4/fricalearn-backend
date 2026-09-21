<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWeeklyFeedback extends Command
{
    protected $signature   = 'reports:weekly-feedback {--test-parent= : Run for a specific parent email only}';
    protected $description = 'Email each parent a weekly progress summary for their child';

    public function handle(): int
    {
        $weekStart = now()->subDays(7);
        $testEmail = $this->option('test-parent');

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
                $this->line("Processing: {$pair->child_name}");

                // ── Gather week stats ──────────────────────────────────
                $quizzes = DB::table('quiz_performance')
                    ->where('student_id', $pair->child_id)
                    ->where('completed_at', '>=', $weekStart)
                    ->get();

                $lessonsCompleted = DB::table('user_external_lesson_progress')
                    ->where('user_id', $pair->child_id)
                    ->where('status', 'completed')
                    ->where('completed_at', '>=', $weekStart)
                    ->count();

                $points = DB::table('student_profiles')
                    ->where('user_id', $pair->child_id)
                    ->value('total_points') ?? 0;

                $avgScore = $quizzes->isNotEmpty() ? round($quizzes->avg('score')) : null;
                $passRate = $quizzes->count() > 0
                    ? round($quizzes->where('passed', 1)->count() / $quizzes->count() * 100)
                    : null;

                // ── Weak topics this week ──────────────────────────────
                $weakTopics = DB::table('quiz_performance as qp')
                    ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                    ->where('qp.student_id', $pair->child_id)
                    ->where('qp.completed_at', '>=', $weekStart)
                    ->where('qp.passed', 0)
                    ->select('t.title', DB::raw('COUNT(*) as fails'), DB::raw('AVG(qp.score) as avg'))
                    ->groupBy('t.id', 't.title')
                    ->orderByDesc('fails')
                    ->limit(3)
                    ->get();

                // ── Strong topics this week ────────────────────────────
                $strongTopics = DB::table('quiz_performance as qp')
                    ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                    ->where('qp.student_id', $pair->child_id)
                    ->where('qp.completed_at', '>=', $weekStart)
                    ->where('qp.score', '>=', 80)
                    ->select('t.title', DB::raw('MAX(qp.score) as best'))
                    ->groupBy('t.id', 't.title')
                    ->orderByDesc('best')
                    ->limit(2)
                    ->get();

                // ── No activity ────────────────────────────────────────
                if ($lessonsCompleted === 0 && $quizzes->isEmpty()) {
                    $this->buildAndSendWeekly(
                        $pair, 'inactive', $lessonsCompleted,
                        $avgScore, $passRate, $weakTopics, $strongTopics, $points
                    );
                    $sent++;
                    continue;
                }

                // ── Determine tone ─────────────────────────────────────
                $tone = 'steady';
                if ($avgScore !== null && $avgScore >= 80 && $lessonsCompleted >= 3) $tone = 'excellent';
                elseif ($avgScore !== null && $avgScore >= 70)                        $tone = 'good';
                elseif ($avgScore !== null && $avgScore < 50)                         $tone = 'struggling';

                $this->buildAndSendWeekly(
                    $pair, $tone, $lessonsCompleted,
                    $avgScore, $passRate, $weakTopics, $strongTopics, $points
                );
                $sent++;

            } catch (\Exception $e) {
                Log::error("Weekly report failed for child {$pair->child_id}: " . $e->getMessage());
                $this->warn("  ❌ Failed: " . $e->getMessage());
            }
        }

        $this->info("Weekly reports sent: {$sent}");
        return self::SUCCESS;
    }

    private function buildAndSendWeekly($pair, string $tone, int $lessons, $avgScore, $passRate, $weakTopics, $strongTopics, int $points): void
    {
        $name = e($pair->child_name);

        // Headline
        $headlines = [
            'excellent'  => ["🌟 An outstanding week for {$name}!", "#1A7A4A"],
            'good'       => ["👍 A solid week of progress, {$name}!", "#3F2171"],
            'steady'     => ["📚 {$name} kept learning this week", "#3F2171"],
            'struggling' => ["💪 {$name} needs a little extra support", "#b45309"],
            'inactive'   => ["👋 {$name} missed learning this week", "#b45309"],
        ];
        [$headline, $headColor] = $headlines[$tone];

        // Body message
        $bodyMessages = [
            'excellent'  => "{$name} had a fantastic week — completing <strong>{$lessons} lessons</strong> with an average quiz score of <strong>{$avgScore}%</strong>. Please pass on our congratulations! Consistent high performance like this builds lasting confidence.",
            'good'       => "{$name} completed <strong>{$lessons} lesson(s)</strong> this week" . ($avgScore ? " with an average quiz score of <strong>{$avgScore}%</strong>" : "") . ". Steady, consistent practice is exactly how strong learners are built.",
            'steady'     => "{$name} completed <strong>{$lessons} lesson(s)</strong> this week. Every lesson completed is a step forward — encourage them to push for a quiz or two next week to reinforce the learning.",
            'struggling' => "{$name} completed <strong>{$lessons} lesson(s)</strong> this week" . ($avgScore ? " with an average quiz score of <strong>{$avgScore}%</strong>" : "") . ". The scores suggest some topics need more practice. The AI Tutor is available 24/7 to help work through any difficulties.",
            'inactive'   => "{$name} didn't complete any lessons this week. Life gets busy — a little encouragement from you can make a big difference. Even 15 minutes of learning keeps the momentum going.",
        ];

        // Recommendations
        $recommendations = $this->generateWeeklyRecommendations($tone, $weakTopics, $strongTopics, $lessons, $avgScore);

        // Stats boxes HTML
        $statsHtml = '';
        if ($lessons > 0 || $avgScore !== null) {
            $statsHtml = "
            <table width='100%' style='margin:20px 0;border-collapse:separate;border-spacing:8px;'>
              <tr>
                <td style='background:#f3effa;border-radius:12px;padding:16px;text-align:center;'>
                  <div style='font-size:28px;font-weight:900;color:#3F2171;'>{$lessons}</div>
                  <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:bold;'>Lessons</div>
                </td>" .
                ($avgScore !== null ? "
                <td style='background:#f3effa;border-radius:12px;padding:16px;text-align:center;'>
                  <div style='font-size:28px;font-weight:900;color:#3F2171;'>{$avgScore}%</div>
                  <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:bold;'>Avg Score</div>
                </td>" : "") .
                ($passRate !== null ? "
                <td style='background:#f3effa;border-radius:12px;padding:16px;text-align:center;'>
                  <div style='font-size:28px;font-weight:900;color:#3F2171;'>{$passRate}%</div>
                  <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:bold;'>Pass Rate</div>
                </td>" : "") . "
                <td style='background:#f3effa;border-radius:12px;padding:16px;text-align:center;'>
                  <div style='font-size:28px;font-weight:900;color:#3F2171;'>{$points}</div>
                  <div style='font-size:10px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:bold;'>Total XP</div>
                </td>
              </tr>
            </table>";
        }

        // Strengths
        $strengthsHtml = '';
        if ($strongTopics->isNotEmpty()) {
            $rows = $strongTopics->map(function($t) { return 
                "<li style='margin:6px 0;'>✅ <strong>" . e($t->title) . "</strong> — scored {$t->best}%</li>"
            )->implode('');
            $strengthsHtml = "
            <div style='background:#e8f5e9;border-left:4px solid #1A7A4A;border-radius:0 12px 12px 0;padding:16px;margin:16px 0;'>
              <p style='font-weight:900;color:#1A7A4A;margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:1px;'>⭐ Doing Well</p>
              <ul style='margin:0;padding-left:20px;color:#333;font-size:14px;'>{$rows}</ul>
            </div>";
        }

        // Weak areas
        $weakHtml = '';
        if ($weakTopics->isNotEmpty()) {
            $rows = $weakTopics->map(function($t) { return 
                "<li style='margin:6px 0;'>⚠️ <strong>" . e($t->title) . "</strong> — " . round($t->avg) . "% avg</li>"
            )->implode('');
            $weakHtml = "
            <div style='background:#fff8e1;border-left:4px solid #b45309;border-radius:0 12px 12px 0;padding:16px;margin:16px 0;'>
              <p style='font-weight:900;color:#b45309;margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:1px;'>📌 Needs More Practice</p>
              <ul style='margin:0;padding-left:20px;color:#333;font-size:14px;'>{$rows}</ul>
            </div>";
        }

        // Recommendations
        $recsHtml = '';
        if (!empty($recommendations)) {
            $items = implode('', array_map(
                 function($r) { return "<li style='margin:8px 0;color:#333;font-size:14px;line-height:1.5;'>💡 {$r}</li>"; },
                $recommendations
            ));
            $recsHtml = "
            <div style='background:#f3effa;border-radius:12px;padding:16px;margin:16px 0;'>
              <p style='font-weight:900;color:#3F2171;margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:1px;'>📋 This Week's Recommendations</p>
              <ul style='margin:0;padding-left:20px;'>{$items}</ul>
            </div>";
        }

        $weekStr  = now()->subDays(7)->format('d M') . ' – ' . now()->format('d M Y');
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
          <div style='background:#2A1650;padding:28px;border-radius:16px 16px 0 0;'>
            <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#FFFF00;'>Learn</span></h1>
            <p style='color:#ffffff80;margin:6px 0 0;font-size:13px;'>Weekly Progress Report · {$weekStr}</p>
          </div>
          <div style='background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;'>
            <p style='font-size:15px;color:#333;'>Dear " . e($pair->parent_name) . ",</p>
            <h2 style='color:{$headColor};font-size:20px;margin:12px 0;'>{$headline}</h2>
            <p style='line-height:1.7;color:#555;font-size:14px;'>{$bodyMessages[$tone]}</p>
            {$statsHtml}
            {$strengthsHtml}
            {$weakHtml}
            {$recsHtml}
            <div style='margin-top:24px;padding-top:20px;border-top:1px solid #eee;'>
              <a href='https://fricalearn.com/parent/dashboard'
                 style='background:#3F2171;color:#fff;padding:13px 28px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block;'>
                Open Parent Dashboard
              </a>
            </div>
            <p style='color:#aaa;font-size:11px;margin-top:24px;'>
              FRICA SOLUTION LIMITED · hello@fricalearn.com · WhatsApp +234 817 448 5504<br>
              You receive this because you are a registered FricaLearn parent.
            </p>
          </div>
        </div>";

        Mail::html($html, function ($m) use ($pair, $name) {
            $m->to($pair->parent_email)
              ->subject("📊 Weekly Report: {$name} — " . now()->format('d M Y'));
        });
        $this->line("  ✅ Sent to {$pair->parent_email}");
    }

    private function generateWeeklyRecommendations(string $tone, $weakTopics, $strongTopics, int $lessons, $avgScore): array
    {
        $recs = [];

        $toneRecs = [
            'excellent'  => "Encourage your child to try the Leaderboard — they are likely ranking highly and recognition boosts motivation.",
            'good'       => "Ask your child to explain one thing they learned this week — teaching it back deepens understanding.",
            'steady'     => "Try setting a small daily goal: even 2 lessons per day adds up to over 60 lessons per month.",
            'struggling' => "Encourage your child to use the AI Tutor before attempting a quiz — it can explain concepts in a way that builds confidence.",
            'inactive'   => "Set a specific 20-minute learning slot together — consistency is more important than duration.",
        ];
        if (isset($toneRecs[$tone])) { $recs[] = $toneRecs[$tone]; }

        if ($weakTopics->isNotEmpty()) {
            $topic = e($weakTopics->first()->title);
            $recs[] = "Focus on <strong>{$topic}</strong> this week — revisit the lesson before attempting the quiz again.";
            if ($weakTopics->count() > 1) {
                $recs[] = "Don't try to fix everything at once — one topic done well is better than three topics done poorly.";
            }
        }

        if ($strongTopics->isNotEmpty()) {
            $recs[] = "Build on this week's strengths — the next topic after <strong>" . e($strongTopics->first()->title) . "</strong> is a natural next step.";
        }

        if ($lessons < 3 && $tone !== 'inactive') {
            $recs[] = "Aim for at least 3 lessons next week — short, regular sessions are more effective than long, infrequent ones.";
        }

        if ($avgScore !== null && $avgScore < 60) {
            $recs[] = "A score below 60% usually means the lesson content needs a second read — encourage going back before retaking the quiz.";
        }

        return array_slice($recs, 0, 3);
    }
}

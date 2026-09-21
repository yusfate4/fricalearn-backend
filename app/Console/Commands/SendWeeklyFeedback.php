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
        $weekStr   = now()->subDays(7)->format('d M') . ' to ' . now()->format('d M Y');
        $testEmail = $this->option('test-parent');

        $query = DB::table('parent_child as pc')
            ->join('users as p', 'p.id', '=', 'pc.parent_id')
            ->join('users as c', 'c.id', '=', 'pc.child_id')
            ->where('c.is_active', 1)
            ->select(
                'p.email as parent_email', 'p.name as parent_name',
                'c.id as child_id', 'c.name as child_name'
            );

        if ($testEmail) {
            $query->where('p.email', $testEmail);
        }

        $pairs = $query->get();
        $sent  = 0;

        foreach ($pairs as $pair) {
            try {
                $this->line('Processing: ' . $pair->child_name);

                $quizzes = DB::table('quiz_performance')
                    ->where('student_id', $pair->child_id)
                    ->where('completed_at', '>=', $weekStart)
                    ->get();

                $lessons = DB::table('user_external_lesson_progress')
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

                // Weak topics
                $weakTopics = DB::table('quiz_performance as qp')
                    ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                    ->where('qp.student_id', $pair->child_id)
                    ->where('qp.completed_at', '>=', $weekStart)
                    ->where('qp.passed', 0)
                    ->select('t.title', DB::raw('COUNT(*) as fails'), DB::raw('AVG(qp.score) as avg_score'))
                    ->groupBy('t.id', 't.title')
                    ->orderByDesc('fails')
                    ->limit(3)
                    ->get();

                // Strong topics
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

                // Determine tone
                $tone = 'steady';
                if ($lessons === 0 && $quizzes->isEmpty()) {
                    $tone = 'inactive';
                } elseif ($avgScore !== null && $avgScore >= 80 && $lessons >= 3) {
                    $tone = 'excellent';
                } elseif ($avgScore !== null && $avgScore >= 70) {
                    $tone = 'good';
                } elseif ($avgScore !== null && $avgScore < 50) {
                    $tone = 'struggling';
                }

                $html = $this->buildHtml(
                    $pair, $tone, $lessons, $avgScore,
                    $passRate, $weakTopics, $strongTopics, $points, $weekStr
                );

                Mail::html($html, function ($m) use ($pair) {
                    $m->to($pair->parent_email)
                      ->subject('Weekly Report: ' . $pair->child_name . ' - ' . now()->format('d M Y'));
                });

                $this->line('  Sent to ' . $pair->parent_email);
                $sent++;

            } catch (\Exception $e) {
                Log::error('Weekly report failed for child ' . $pair->child_id . ': ' . $e->getMessage());
                $this->warn('  Failed: ' . $e->getMessage());
            }
        }

        $this->info('Weekly reports sent: ' . $sent);
        return self::SUCCESS;
    }

    private function buildHtml($pair, string $tone, int $lessons, $avgScore, $passRate, $weakTopics, $strongTopics, int $points, string $weekStr): string
    {
        $name   = e($pair->child_name);
        $parent = e($pair->parent_name);

        $toneConfig = [
            'excellent'  => ['headline' => 'An outstanding week for ' . $name . '!',   'color' => '#1A7A4A'],
            'good'       => ['headline' => 'A solid week of progress, ' . $name . '!', 'color' => '#3F2171'],
            'steady'     => ['headline' => $name . ' kept learning this week',          'color' => '#3F2171'],
            'struggling' => ['headline' => $name . ' needs a little extra support',     'color' => '#b45309'],
            'inactive'   => ['headline' => $name . ' missed learning this week',        'color' => '#b45309'],
        ];
        $cfg       = isset($toneConfig[$tone]) ? $toneConfig[$tone] : $toneConfig['steady'];
        $headline  = $cfg['headline'];
        $headColor = $cfg['color'];

        $bodyMap = [
            'excellent'  => $name . ' completed <strong>' . $lessons . ' lessons</strong> with an average score of <strong>' . $avgScore . '%</strong>. Outstanding effort - please pass on our congratulations!',
            'good'       => $name . ' completed <strong>' . $lessons . ' lesson(s)</strong>' . ($avgScore ? ' with an average score of <strong>' . $avgScore . '%</strong>' : '') . '. Consistent practice is how strong learners are built.',
            'steady'     => $name . ' completed <strong>' . $lessons . ' lesson(s)</strong> this week. Every lesson is a step forward.',
            'struggling' => $name . ' completed <strong>' . $lessons . ' lesson(s)</strong>' . ($avgScore ? ' with an average score of <strong>' . $avgScore . '%</strong>' : '') . '. Some topics need more practice. The AI Tutor is available 24/7 to help.',
            'inactive'   => $name . ' did not complete any lessons this week. Even 15 minutes of learning keeps the momentum going.',
        ];
        $body = isset($bodyMap[$tone]) ? $bodyMap[$tone] : $bodyMap['steady'];

        // Stats row
        $statsHtml = '';
        if ($lessons > 0 || $avgScore !== null) {
            $statsHtml  = '<table width="100%" style="margin:20px 0;border-collapse:separate;border-spacing:6px;">';
            $statsHtml .= '<tr>';
            $statsHtml .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;">';
            $statsHtml .= '<div style="font-size:26px;font-weight:900;color:#3F2171;">' . $lessons . '</div>';
            $statsHtml .= '<div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Lessons</div></td>';
            if ($avgScore !== null) {
                $statsHtml .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;">';
                $statsHtml .= '<div style="font-size:26px;font-weight:900;color:#3F2171;">' . $avgScore . '%</div>';
                $statsHtml .= '<div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Avg Score</div></td>';
            }
            if ($passRate !== null) {
                $statsHtml .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;">';
                $statsHtml .= '<div style="font-size:26px;font-weight:900;color:#3F2171;">' . $passRate . '%</div>';
                $statsHtml .= '<div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Pass Rate</div></td>';
            }
            $statsHtml .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;">';
            $statsHtml .= '<div style="font-size:26px;font-weight:900;color:#3F2171;">' . $points . '</div>';
            $statsHtml .= '<div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Total XP</div></td>';
            $statsHtml .= '</tr></table>';
        }

        // Strengths
        $strengthsHtml = '';
        if ($strongTopics->isNotEmpty()) {
            $strengthsHtml  = '<div style="background:#e8f5e9;border-left:4px solid #1A7A4A;border-radius:0 12px 12px 0;padding:16px;margin:16px 0;">';
            $strengthsHtml .= '<p style="font-weight:900;color:#1A7A4A;margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Doing Well</p>';
            $strengthsHtml .= '<ul style="margin:0;padding-left:20px;color:#333;font-size:14px;">';
            foreach ($strongTopics as $t) {
                $strengthsHtml .= '<li style="margin:4px 0;">' . e($t->title) . ' - scored ' . $t->best . '%</li>';
            }
            $strengthsHtml .= '</ul></div>';
        }

        // Weak areas
        $weakHtml = '';
        if ($weakTopics->isNotEmpty()) {
            $weakHtml  = '<div style="background:#fff8e1;border-left:4px solid #b45309;border-radius:0 12px 12px 0;padding:16px;margin:16px 0;">';
            $weakHtml .= '<p style="font-weight:900;color:#b45309;margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Needs More Practice</p>';
            $weakHtml .= '<ul style="margin:0;padding-left:20px;color:#333;font-size:14px;">';
            foreach ($weakTopics as $t) {
                $weakHtml .= '<li style="margin:4px 0;">' . e($t->title) . ' - ' . round($t->avg_score) . '% avg</li>';
            }
            $weakHtml .= '</ul></div>';
        }

        // Recommendations
        $recs     = $this->getRecommendations($tone, $weakTopics, $strongTopics, $lessons, $avgScore);
        $recsHtml = '';
        if (!empty($recs)) {
            $recsHtml  = '<div style="background:#f3effa;border-radius:12px;padding:16px;margin:16px 0;">';
            $recsHtml .= '<p style="font-weight:900;color:#3F2171;margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">This Week\'s Recommendations</p>';
            $recsHtml .= '<ul style="margin:0;padding-left:20px;">';
            foreach ($recs as $r) {
                $recsHtml .= '<li style="margin:8px 0;color:#333;font-size:14px;line-height:1.5;">' . $r . '</li>';
            }
            $recsHtml .= '</ul></div>';
        }

        $html  = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">';
        $html .= '<div style="background:#2A1650;padding:28px;border-radius:16px 16px 0 0;">';
        $html .= '<h1 style="color:#fff;margin:0;font-size:20px;">FricaLearn</h1>';
        $html .= '<p style="color:rgba(255,255,255,0.5);margin:6px 0 0;font-size:13px;">Weekly Progress Report - ' . $weekStr . '</p>';
        $html .= '</div>';
        $html .= '<div style="background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;">';
        $html .= '<p style="font-size:15px;color:#333;">Dear ' . $parent . ',</p>';
        $html .= '<h2 style="color:' . $headColor . ';font-size:18px;margin:12px 0;">' . $headline . '</h2>';
        $html .= '<p style="line-height:1.7;color:#555;font-size:14px;">' . $body . '</p>';
        $html .= $statsHtml;
        $html .= $strengthsHtml;
        $html .= $weakHtml;
        $html .= $recsHtml;
        $html .= '<div style="margin-top:24px;padding-top:20px;border-top:1px solid #eee;">';
        $html .= '<a href="https://fricalearn.com/parent/dashboard" style="background:#3F2171;color:#fff;padding:13px 28px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block;">Open Parent Dashboard</a>';
        $html .= '</div>';
        $html .= '<p style="color:#aaa;font-size:11px;margin-top:24px;">FRICA SOLUTION LIMITED - hello@fricalearn.com - WhatsApp +234 817 448 5504</p>';
        $html .= '</div></div>';
        return $html;
    }

    private function getRecommendations(string $tone, $weakTopics, $strongTopics, int $lessons, $avgScore): array
    {
        $recs = [];

        $toneRecs = [
            'excellent'  => 'Encourage your child to try the Leaderboard - they are likely ranking highly!',
            'good'       => 'Ask your child to explain one thing they learned this week - teaching deepens understanding.',
            'steady'     => 'Try setting a small daily goal: even 2 lessons per day adds up to 60+ lessons per month.',
            'struggling' => 'Encourage your child to use the AI Tutor before quizzes - it builds confidence.',
            'inactive'   => 'Set a specific 20-minute learning slot together - consistency beats duration.',
        ];
        if (isset($toneRecs[$tone])) {
            $recs[] = $toneRecs[$tone];
        }

        if ($weakTopics->isNotEmpty()) {
            $recs[] = 'Focus on <strong>' . e($weakTopics->first()->title) . '</strong> this week - revisit the lesson before retrying the quiz.';
        }

        if ($strongTopics->isNotEmpty()) {
            $recs[] = 'Build on the strength in <strong>' . e($strongTopics->first()->title) . '</strong> - move to the next topic while confidence is high.';
        }

        if ($lessons < 3 && $tone !== 'inactive') {
            $recs[] = 'Aim for at least 3 lessons next week - short, regular sessions work better than long, infrequent ones.';
        }

        return array_slice($recs, 0, 3);
    }
}

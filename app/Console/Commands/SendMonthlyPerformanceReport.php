<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendMonthlyPerformanceReport extends Command
{
    protected $signature   = 'reports:monthly-performance {--current : Use current month} {--test-parent= : Run for one parent email only}';
    protected $description = 'Email each parent a detailed monthly report with strengths, weak areas and recommendations';

    public function handle(): int
    {
        $base       = $this->option('current') ? now() : now()->subMonth();
        $monthStart = $base->copy()->startOfMonth();
        $monthEnd   = $base->copy()->endOfMonth();
        $monthName  = $monthStart->format('F Y');
        $testEmail  = $this->option('test-parent');

        $this->info('Running monthly report for: ' . $monthName);

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
                $this->line('Building report: ' . $pair->child_name);

                $quizzes = DB::table('quiz_performance')
                    ->where('student_id', $pair->child_id)
                    ->whereBetween('completed_at', [$monthStart, $monthEnd])
                    ->get();

                $lessonsCompleted = DB::table('user_external_lesson_progress')
                    ->where('user_id', $pair->child_id)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$monthStart, $monthEnd])
                    ->count();

                if ($quizzes->isEmpty() && $lessonsCompleted === 0) {
                    $this->line('  Skipped (no activity)');
                    continue;
                }

                $avgScore  = $quizzes->isNotEmpty() ? round($quizzes->avg('score')) : 0;
                $totalQuiz = $quizzes->count();
                $passCount = $quizzes->where('passed', 1)->count();
                $passRate  = $totalQuiz > 0 ? round($passCount / $totalQuiz * 100) : 0;
                $totalXP   = (int) DB::table('student_profiles')->where('user_id', $pair->child_id)->value('total_points');
                $level     = DB::table('student_profiles')->where('user_id', $pair->child_id)->value('current_level') ?? 'Beginner';

                // Strong topics
                $strongTopics = DB::table('quiz_performance as qp')
                    ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                    ->join('external_subjects as s', 's.id', '=', 't.subject_id')
                    ->where('qp.student_id', $pair->child_id)
                    ->whereBetween('qp.completed_at', [$monthStart, $monthEnd])
                    ->where('qp.passed', 1)
                    ->select('t.title', 's.name as subject', DB::raw('ROUND(AVG(qp.score)) as avg_score'))
                    ->groupBy('t.id', 't.title', 's.id', 's.name')
                    ->having('avg_score', '>=', 75)
                    ->orderByDesc('avg_score')
                    ->limit(5)
                    ->get();

                // Weak topics
                $weakTopics = DB::table('quiz_performance as qp')
                    ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
                    ->join('external_subjects as s', 's.id', '=', 't.subject_id')
                    ->where('qp.student_id', $pair->child_id)
                    ->whereBetween('qp.completed_at', [$monthStart, $monthEnd])
                    ->select('t.title', 's.name as subject', DB::raw('ROUND(AVG(qp.score)) as avg_score'), DB::raw('SUM(CASE WHEN qp.passed = 0 THEN 1 ELSE 0 END) as fails'))
                    ->groupBy('t.id', 't.title', 's.id', 's.name')
                    ->having('avg_score', '<', 60)
                    ->orderBy('avg_score')
                    ->limit(5)
                    ->get();

                // Week-by-week breakdown
                $weeklyBreakdown = [];
                for ($i = 0; $i < 4; $i++) {
                    $ws = $monthStart->copy()->addDays($i * 7);
                    $we = (clone $ws)->addDays(6);
                    if ($we->gt($monthEnd)) { $we = clone $monthEnd; }
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
                        'label'   => 'Week ' . ($i + 1),
                        'lessons' => $wLessons,
                        'score'   => $wQuizzes->isNotEmpty() ? round($wQuizzes->avg('score')) : null,
                    ];
                }

                $grade = $this->calcGrade($avgScore, $passRate, $lessonsCompleted);
                $recs  = $this->getRecommendations($avgScore, $passRate, $lessonsCompleted, $weakTopics, $strongTopics, $weeklyBreakdown);

                $html = $this->buildHtml(
                    $pair, $monthName, $grade, $lessonsCompleted,
                    $avgScore, $passRate, $totalXP, $level,
                    $strongTopics, $weakTopics, $weeklyBreakdown, $recs
                );

                Mail::html($html, function ($m) use ($pair, $monthName) {
                    $m->to($pair->parent_email)
                      ->subject('Monthly Report: ' . $pair->child_name . ' - ' . $monthName);
                });

                $this->line('  Sent to ' . $pair->parent_email);
                $sent++;

            } catch (\Exception $e) {
                Log::error('Monthly report failed for child ' . $pair->child_id . ': ' . $e->getMessage());
                $this->warn('  Failed: ' . $e->getMessage());
            }
        }

        $this->info('Monthly reports sent: ' . $sent . '/' . $pairs->count());
        return self::SUCCESS;
    }

    private function calcGrade(int $avg, int $passRate, int $lessons): array
    {
        if ($avg >= 80 && $passRate >= 75 && $lessons >= 10) {
            return ['A', '#1A7A4A', 'Excellent - your child is thriving'];
        }
        if ($avg >= 70 && $passRate >= 60 && $lessons >= 6) {
            return ['B', '#2563EB', 'Good - solid and consistent progress'];
        }
        if ($avg >= 60 || $lessons >= 4) {
            return ['C', '#7C3AED', 'Steady - building the right habits'];
        }
        if ($lessons > 0) {
            return ['D', '#B45309', 'Needs support - more practice required'];
        }
        return ['-', '#9CA3AF', 'Insufficient data this month'];
    }

    private function getRecommendations(int $avg, int $passRate, int $lessons, $weak, $strong, array $weekly): array
    {
        $recs = [];

        $activeWeeks = 0;
        foreach ($weekly as $w) {
            if ($w['lessons'] > 0) { $activeWeeks++; }
        }

        if ($activeWeeks <= 1) {
            $recs[] = [
                'title' => 'Build a weekly learning habit',
                'body'  => 'Your child was only active in ' . $activeWeeks . ' of the 4 weeks. Even 15 minutes daily produces better results than longer, infrequent sessions.',
            ];
        } elseif ($activeWeeks === 4) {
            $recs[] = [
                'title' => 'Outstanding consistency!',
                'body'  => 'Your child was active every week this month. This regularity is the single biggest predictor of academic progress.',
            ];
        }

        if ($weak->isNotEmpty()) {
            $topic = e($weak->first()->title);
            $score = $weak->first()->avg_score;
            $recs[] = [
                'title' => 'Focus area: ' . $topic,
                'body'  => 'Average score of ' . $score . '% - this topic needs more attention. Encourage re-reading the lesson before retrying the quiz, and using the AI Tutor when stuck.',
            ];
        }

        if ($avg > 0 && $avg < 65) {
            $recs[] = [
                'title' => 'Read before quizzing',
                'body'  => 'An average score of ' . $avg . '% suggests lessons are not being read fully before the quiz. Encourage reading each lesson from start to finish first.',
            ];
        }

        if ($strong->isNotEmpty()) {
            $recs[] = [
                'title' => 'Build on this month\'s strengths',
                'body'  => 'Your child excelled at ' . e($strong->first()->title) . '. The next topic builds directly on these skills - move forward while confidence is high.',
            ];
        }

        if ($lessons < 4) {
            $recs[] = [
                'title' => 'Aim for more lessons next month',
                'body'  => 'Only ' . $lessons . ' lesson(s) completed this month. Even 2 lessons per week - 8 per month - is enough to see meaningful curriculum progression.',
            ];
        }

        return array_slice($recs, 0, 4);
    }

    private function buildHtml($pair, string $month, array $grade, int $lessons, int $avg, int $passRate, int $xp, string $level, $strong, $weak, array $weekly, array $recs): string
    {
        $name   = e($pair->child_name);
        $parent = e($pair->parent_name);

        list($gradeLabel, $gradeColor, $gradeMsg) = $grade;

        // Stats
        $html  = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">';
        $html .= '<div style="background:#2A1650;padding:28px;border-radius:16px 16px 0 0;">';
        $html .= '<h1 style="color:#fff;margin:0;font-size:20px;">FricaLearn</h1>';
        $html .= '<p style="color:rgba(255,255,255,0.5);margin:6px 0 0;font-size:13px;">Monthly Progress Report - ' . $month . '</p>';
        $html .= '</div>';
        $html .= '<div style="background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;">';
        $html .= '<p style="font-size:15px;color:#333;">Dear ' . $parent . ',</p>';
        $html .= '<p style="font-size:14px;line-height:1.7;color:#555;">Here is <strong>' . $name . '\'s</strong> full learning report for ' . $month . '.</p>';

        // Grade badge
        $html .= '<div style="background:' . $gradeColor . ';border-radius:16px;padding:20px;margin:20px 0;">';
        $html .= '<table width="100%"><tr>';
        $html .= '<td style="width:80px;"><div style="background:rgba(255,255,255,0.2);border-radius:12px;padding:8px 16px;text-align:center;">';
        $html .= '<div style="font-size:36px;font-weight:900;color:#fff;line-height:1;">' . $gradeLabel . '</div>';
        $html .= '<div style="font-size:9px;color:rgba(255,255,255,0.8);text-transform:uppercase;letter-spacing:1px;font-weight:700;">Grade</div>';
        $html .= '</div></td>';
        $html .= '<td style="padding-left:16px;"><p style="color:#fff;font-weight:900;margin:0;font-size:16px;">' . $gradeMsg . '</p>';
        $html .= '<p style="color:rgba(255,255,255,0.7);margin:4px 0 0;font-size:12px;">Rank: ' . $level . ' - ' . $xp . ' total XP</p></td>';
        $html .= '</tr></table></div>';

        // Stats boxes
        $html .= '<table width="100%" style="border-collapse:separate;border-spacing:6px;margin:20px 0;">';
        $html .= '<tr>';
        $html .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;"><div style="font-size:26px;font-weight:900;color:#3F2171;">' . $lessons . '</div><div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Lessons</div></td>';
        $html .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;"><div style="font-size:26px;font-weight:900;color:#3F2171;">' . $avg . '%</div><div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Avg Score</div></td>';
        $html .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;"><div style="font-size:26px;font-weight:900;color:#3F2171;">' . $passRate . '%</div><div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Pass Rate</div></td>';
        $html .= '<td style="background:#f3effa;border-radius:10px;padding:14px 8px;text-align:center;"><div style="font-size:26px;font-weight:900;color:#3F2171;">' . $xp . '</div><div style="font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Total XP</div></td>';
        $html .= '</tr></table>';

        // Week-by-week
        $html .= '<div style="background:#f9f7ff;border-radius:12px;padding:16px;margin:16px 0;">';
        $html .= '<p style="font-weight:900;color:#3F2171;margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Week-by-week activity</p>';
        $html .= '<table style="width:100%;">';
        foreach ($weekly as $w) {
            $bar   = str_repeat('|', max(0, $w['lessons'] * 3));
            $score = $w['score'] !== null ? ' - ' . $w['score'] . '% avg' : '';
            $html .= '<tr>';
            $html .= '<td style="font-size:11px;font-weight:700;color:#555;padding:4px 8px 4px 0;width:60px;">' . $w['label'] . '</td>';
            $html .= '<td style="font-size:11px;color:#3F2171;padding:4px;">' . $bar . '</td>';
            $html .= '<td style="font-size:11px;color:#888;padding:4px;">' . $w['lessons'] . ' lesson(s)' . $score . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></div>';

        // Strong topics
        if ($strong->isNotEmpty()) {
            $html .= '<div style="background:#e8f5e9;border-radius:12px;padding:16px;margin:16px 0;">';
            $html .= '<p style="font-weight:900;color:#1A7A4A;margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Areas of Strength</p>';
            $html .= '<table style="width:100%;">';
            foreach ($strong as $t) {
                $html .= '<tr>';
                $html .= '<td style="padding:6px 0;font-size:13px;color:#333;border-bottom:1px solid #c8e6c9;">' . e($t->title) . '<br><span style="font-size:10px;color:#888;">' . e($t->subject) . '</span></td>';
                $html .= '<td style="padding:6px 0;text-align:right;font-weight:900;color:#1A7A4A;font-size:14px;border-bottom:1px solid #c8e6c9;">' . $t->avg_score . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</table></div>';
        }

        // Weak topics
        if ($weak->isNotEmpty()) {
            $html .= '<div style="background:#fff8e1;border-radius:12px;padding:16px;margin:16px 0;">';
            $html .= '<p style="font-weight:900;color:#b45309;margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Areas to Improve</p>';
            $html .= '<table style="width:100%;">';
            foreach ($weak as $t) {
                $html .= '<tr>';
                $html .= '<td style="padding:6px 0;font-size:13px;color:#333;border-bottom:1px solid #ffe0b2;">' . e($t->title) . '<br><span style="font-size:10px;color:#888;">' . e($t->subject) . '</span></td>';
                $html .= '<td style="padding:6px 0;text-align:right;font-weight:900;color:#b45309;font-size:14px;border-bottom:1px solid #ffe0b2;">' . $t->avg_score . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</table></div>';
        }

        // Recommendations
        if (!empty($recs)) {
            $html .= '<div style="margin:20px 0;">';
            $html .= '<p style="font-weight:900;color:#2A1650;margin:0 0 12px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Recommendations for Next Month</p>';
            foreach ($recs as $r) {
                $html .= '<div style="border-left:3px solid #3F2171;padding:10px 14px;margin:10px 0;background:#faf9ff;border-radius:0 8px 8px 0;">';
                $html .= '<p style="font-weight:900;color:#3F2171;margin:0 0 4px;font-size:13px;">' . $r['title'] . '</p>';
                $html .= '<p style="color:#555;font-size:13px;line-height:1.6;margin:0;">' . $r['body'] . '</p>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '<div style="margin-top:28px;padding-top:20px;border-top:1px solid #eee;">';
        $html .= '<a href="https://fricalearn.com/parent/dashboard" style="background:#3F2171;color:#fff;padding:14px 28px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:14px;display:inline-block;">Open Dashboard</a>';
        $html .= '</div>';
        $html .= '<p style="color:#aaa;font-size:11px;margin-top:24px;line-height:1.6;">FRICA SOLUTION LIMITED - hello@fricalearn.com - WhatsApp +234 817 448 5504<br>Curriculum content (c) Oak National Academy.</p>';
        $html .= '</div></div>';

        return $html;
    }
}

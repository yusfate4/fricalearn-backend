<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChildProgressController extends Controller
{
    /**
     * GET /parent/child/{childId}/progress
     * Returns full progress data for a child — used by the in-portal progress tracker.
     */
    public function getProgress(Request $request, $childId)
    {
        $parent   = $request->user();

        if (!$parent) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $childIds = $this->getLinkedChildIds($parent);

        // Also allow admin to view any child's progress
        if (!in_array($childId, $childIds) && $parent->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorised access to this student.',
                'debug'   => 'Parent ' . $parent->id . ' does not own child ' . $childId . '. Linked children: ' . implode(',', $childIds)
            ], 403);
        }

        $child = User::with('studentProfile')->findOrFail($childId);

        // ── Date ranges ───────────────────────────────────────────
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();
        $weekStart  = now()->subDays(7);

        // ── All-time stats ────────────────────────────────────────
        $totalLessons = DB::table('user_external_lesson_progress')
            ->where('user_id', $childId)
            ->where('status', 'completed')
            ->count();

        $allQuizzes = DB::table('quiz_performance')
            ->where('student_id', $childId)
            ->get();

        $avgScoreAllTime = $allQuizzes->isNotEmpty() ? round($allQuizzes->avg('score')) : 0;
        $passRateAllTime = $allQuizzes->count() > 0
            ? round($allQuizzes->where('passed', 1)->count() / $allQuizzes->count() * 100)
            : 0;

        $totalXP    = (int) optional($child->studentProfile)->total_points;
        $level      = optional($child->studentProfile)->current_level ?? 'Beginner';

        // ── This month ────────────────────────────────────────────
        $monthQuizzes = DB::table('quiz_performance')
            ->where('student_id', $childId)
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->get();

        $monthLessons = DB::table('user_external_lesson_progress')
            ->where('user_id', $childId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->count();

        $monthAvg  = $monthQuizzes->isNotEmpty() ? round($monthQuizzes->avg('score')) : 0;
        $monthPass = $monthQuizzes->count() > 0
            ? round($monthQuizzes->where('passed', 1)->count() / $monthQuizzes->count() * 100)
            : 0;

        // ── This week ─────────────────────────────────────────────
        $weekLessons = DB::table('user_external_lesson_progress')
            ->where('user_id', $childId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $weekStart)
            ->count();

        $weekQuizzes = DB::table('quiz_performance')
            ->where('student_id', $childId)
            ->where('completed_at', '>=', $weekStart)
            ->get();

        $weekAvg = $weekQuizzes->isNotEmpty() ? round($weekQuizzes->avg('score')) : null;

        // ── Grade ─────────────────────────────────────────────────
        $grade = $this->calcGrade($monthAvg, $monthPass, $monthLessons);

        // ── Week-by-week breakdown (last 4 weeks) ─────────────────
        $weeklyBreakdown = [];
        for ($i = 3; $i >= 0; $i--) {
            $ws = now()->subDays(($i + 1) * 7);
            $we = now()->subDays($i * 7);
            $wL = DB::table('user_external_lesson_progress')
                ->where('user_id', $childId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$ws, $we])
                ->count();
            $wQ = DB::table('quiz_performance')
                ->where('student_id', $childId)
                ->whereBetween('completed_at', [$ws, $we])
                ->get();
            $weeklyBreakdown[] = [
                'label'    => $we->format('d M'),
                'lessons'  => $wL,
                'avg_score'=> $wQ->isNotEmpty() ? round($wQ->avg('score')) : null,
                'quizzes'  => $wQ->count(),
            ];
        }

        // ── Daily activity (last 30 days) ─────────────────────────
        $dailyActivity = DB::table('user_external_lesson_progress')
            ->where('user_id', $childId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as lessons')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // ── Strong topics ─────────────────────────────────────────
        $strongTopics = DB::table('quiz_performance as qp')
            ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
            ->join('external_subjects as s', 's.id', '=', 'qp.subject_id')
            ->where('qp.student_id', $childId)
            ->where('qp.passed', 1)
            ->select(
                't.title',
                's.name as subject',
                DB::raw('ROUND(AVG(qp.score)) as avg_score'),
                DB::raw('COUNT(*) as attempts')
            )
            ->groupBy('qp.topic_id', 't.title', 'qp.subject_id', 's.name')
            ->having('avg_score', '>=', 75)
            ->orderByDesc('avg_score')
            ->limit(8)
            ->get();

        // ── Weak topics ───────────────────────────────────────────
        $weakTopics = DB::table('quiz_performance as qp')
            ->join('external_topics as t', 't.id', '=', 'qp.topic_id')
            ->join('external_subjects as s', 's.id', '=', 'qp.subject_id')
            ->where('qp.student_id', $childId)
            ->select(
                't.title',
                's.name as subject',
                DB::raw('ROUND(AVG(qp.score)) as avg_score'),
                DB::raw('SUM(CASE WHEN qp.passed = 0 THEN 1 ELSE 0 END) as fails'),
                DB::raw('COUNT(*) as attempts')
            )
            ->groupBy('qp.topic_id', 't.title', 'qp.subject_id', 's.name')
            ->having('avg_score', '<', 60)
            ->orderBy('avg_score')
            ->limit(8)
            ->get();

        // ── Recent lessons (last 15) ──────────────────────────────
        $recentLessons = DB::table('user_external_lesson_progress as p')
            ->join('external_lessons as l', 'l.id', '=', 'p.lesson_id')
            ->join('external_topics as t', 't.id', '=', 'l.topic_id')
            ->join('external_subjects as s', 's.id', '=', 't.subject_id')
            ->where('p.user_id', $childId)
            ->where('p.status', 'completed')
            ->select(
                'l.title as lesson_title',
                't.title as topic_title',
                's.name as subject',
                'p.completed_at',
                'p.quiz_score'
            )
            ->orderByDesc('p.completed_at')
            ->limit(15)
            ->get();

        // ── Recommendations ───────────────────────────────────────
        $recs = $this->getRecommendations(
            $monthAvg, $monthPass, $monthLessons, $weakTopics, $strongTopics, $weeklyBreakdown
        );

        // ── Enrolled subjects ─────────────────────────────────────
        $enrolledSubjects = DB::table('user_external_subject_enrollments as e')
            ->join('external_subjects as s', 's.id', '=', 'e.external_subject_id')
            ->where('e.user_id', $childId)
            ->select('s.id', 's.name', 's.key_stage', 'e.progress_percentage')
            ->get();

        return response()->json([
            'success'  => true,
            'child'    => [
                'id'               => $child->id,
                'name'             => $child->name,
                'level'            => $level,
                'total_xp'         => $totalXP,
                'trial_ends_at'    => $child->trial_ends_at,
                'is_premium'       => $child->is_premium,
                'selected_courses' => $child->selected_courses,
            ],
            'grade'    => $grade,
            'overview' => [
                'total_lessons'      => $totalLessons,
                'avg_score_alltime'  => $avgScoreAllTime,
                'pass_rate_alltime'  => $passRateAllTime,
                'total_xp'           => $totalXP,
            ],
            'this_month' => [
                'lessons'   => $monthLessons,
                'avg_score' => $monthAvg,
                'pass_rate' => $monthPass,
                'quizzes'   => $monthQuizzes->count(),
            ],
            'this_week' => [
                'lessons'   => $weekLessons,
                'avg_score' => $weekAvg,
                'quizzes'   => $weekQuizzes->count(),
            ],
            'weekly_breakdown'  => $weeklyBreakdown,
            'daily_activity'    => $dailyActivity,
            'strong_topics'     => $strongTopics,
            'weak_topics'       => $weakTopics,
            'recent_lessons'    => $recentLessons,
            'enrolled_subjects' => $enrolledSubjects,
            'recommendations'   => $recs,
        ]);
    }

    private function calcGrade(int $avg, int $passRate, int $lessons): array
    {
        if ($avg >= 80 && $passRate >= 75 && $lessons >= 10) {
            return ['label' => 'A', 'color' => '#1A7A4A', 'message' => 'Excellent'];
        }
        if ($avg >= 70 && $passRate >= 60 && $lessons >= 6) {
            return ['label' => 'B', 'color' => '#2563EB', 'message' => 'Good'];
        }
        if ($avg >= 60 || $lessons >= 4) {
            return ['label' => 'C', 'color' => '#7C3AED', 'message' => 'Steady'];
        }
        if ($lessons > 0) {
            return ['label' => 'D', 'color' => '#B45309', 'message' => 'Needs support'];
        }
        return ['label' => '-', 'color' => '#9CA3AF', 'message' => 'No data yet'];
    }

    private function getRecommendations(int $avg, int $passRate, int $lessons, $weak, $strong, array $weekly): array
    {
        $recs = [];

        $activeWeeks = count(array_filter($weekly, function ($w) { return $w['lessons'] > 0; }));

        if ($activeWeeks <= 1) {
            $recs[] = ['type' => 'habit', 'title' => 'Build a weekly habit', 'body' => 'Only ' . $activeWeeks . ' active week(s) recently. Even 15 minutes daily produces far better results than long, infrequent sessions.'];
        }

        if ($weak->isNotEmpty()) {
            $recs[] = ['type' => 'focus', 'title' => 'Focus: ' . $weak->first()->title, 'body' => $weak->first()->avg_score . '% average — encourage revisiting the lesson before retrying the quiz.'];
        }

        if ($avg > 0 && $avg < 65) {
            $recs[] = ['type' => 'score', 'title' => 'Read before quizzing', 'body' => 'A ' . $avg . '% average suggests lessons may not be read fully. Encourage a full read-through first.'];
        }

        if ($strong->isNotEmpty()) {
            $recs[] = ['type' => 'strength', 'title' => 'Build on ' . $strong->first()->title, 'body' => 'Excellent score of ' . $strong->first()->avg_score . '% — the next topic builds directly on this.'];
        }

        if ($lessons < 4) {
            $recs[] = ['type' => 'volume', 'title' => 'Aim for more lessons', 'body' => 'Only ' . $lessons . ' lesson(s) this month. 2 lessons per week — 8 per month — is enough for meaningful progress.'];
        }

        return array_slice($recs, 0, 4);
    }

    private function getLinkedChildIds($parent)
    {
        $pivotIds  = $parent->children()->pluck('users.id')->toArray();
        $directIds = \App\Models\User::where('parent_id', $parent->id)->pluck('id')->toArray();
        return array_unique(array_merge($pivotIds, $directIds));
    }
}

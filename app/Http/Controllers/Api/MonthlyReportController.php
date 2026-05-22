<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\MonthlyPerformanceReport;

class MonthlyReportController extends Controller
{
    public function generateMonthlyReport($studentId, $month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $performances = DB::table('quiz_performance as qp')
            ->join('external_topics as t', 'qp.topic_id', '=', 't.id')
            ->join('external_subjects as s', 'qp.subject_id', '=', 's.id')
            ->where('qp.student_id', $studentId)
            ->whereBetween('qp.completed_at', [$startDate, $endDate])
            ->select('s.name as subject', 't.title as topic', 'qp.score', 'qp.passed')
            ->get();
        
        $totalQuizzes = $performances->count();
        if ($totalQuizzes === 0) return null; // No data for this month

        $passedQuizzes = $performances->where('passed', 1)->count();
        $averageScore = $performances->avg('score');
        
        $weakAreas = $performances->groupBy('topic')->map(function ($group) {
            return ['topic' => $group->first()->topic, 'subject' => $group->first()->subject, 'avg_score' => round($group->avg('score'))];
        })->where('avg_score', '<', 70)->values();
        
        $strongAreas = $performances->groupBy('topic')->map(function ($group) {
            return ['topic' => $group->first()->topic, 'subject' => $group->first()->subject, 'avg_score' => round($group->avg('score'))];
        })->where('avg_score', '>=', 85)->values();
        
        return [
            'student_id' => $studentId,
            'month_name' => $startDate->format('F Y'),
            'total_quizzes' => $totalQuizzes,
            'pass_rate' => round(($passedQuizzes / $totalQuizzes) * 100),
            'average_score' => round($averageScore),
            'strong_areas' => $strongAreas,
            'weak_areas' => $weakAreas,
            'needs_tutoring' => $weakAreas->count() > 0,
        ];
    }
    
    public function emailMonthlyReport($studentId)
    {
        $reportData = $this->generateMonthlyReport($studentId);
        
        if (!$reportData) {
            return response()->json(['message' => 'No quiz data found for this month.'], 404);
        }

        $student = DB::table('users')->find($studentId);
        $parent = DB::table('users')->find($student->parent_id);
        
        if ($parent) {
            Mail::to($parent->email)->send(new MonthlyPerformanceReport($student, $reportData));
            return response()->json(['message' => 'Report sent successfully to Parent.']);
        }

        return response()->json(['message' => 'Parent not found.'], 404);
    }

    public function getStudentAnalytics(Request $request, $studentId)
    {
        $weakTopics = DB::table('quiz_performance as qp')
            ->join('external_topics as t', 'qp.topic_id', '=', 't.id')
            ->where('qp.student_id', $studentId)
            ->select('t.title', DB::raw('AVG(qp.score) as avg_score'), DB::raw('COUNT(*) as attempts'))
            ->groupBy('t.title')
            ->having('avg_score', '<', 70)
            ->orderBy('avg_score', 'asc')
            ->get();
        
        return response()->json([
            'weak_topics' => $weakTopics,
            'needs_tutor' => $weakTopics->count() > 0,
            'message' => $weakTopics->count() > 0 ? 'Consider booking 1-on-1 tutoring.' : 'Great progress!'
        ]);
    }
}
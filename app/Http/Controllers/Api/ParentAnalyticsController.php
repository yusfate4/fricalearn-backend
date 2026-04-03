<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// 🚀 ADD THESE MODELS HERE
use App\Models\User;
use App\Models\QuizAttempt; 
use App\Models\ProgressRecord;
use Illuminate\Support\Facades\Response; // For Response::json

class ParentAnalyticsController extends Controller
{
    public function getChildStats(Request $request, $childId)
    {
        // 🛡️ Ensure this child actually belongs to the logged-in parent
        $child = $request->user()->children()->findOrFail($childId);

        // Fetch recent quiz attempts with scores
        $recentQuizzes = QuizAttempt::where('student_id', $childId)
            ->with('quiz.lesson')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        // Weekly Activity (Points earned per day)
        $weeklyActivity = $child->pointsHistory()
            ->where('created_at', '>=', now()->startOfWeek())
            ->selectRaw('DATE(created_at) as date, SUM(points) as total_points')
            ->groupBy('date')
            ->get();

        return response::json([
            'child_name' => $child->name,
            'stats' => [
                'total_points' => $child->studentProfile->total_points,
                'average_score' => $recentQuizzes->avg('score') ?? 0,
                'lessons_completed' => ProgressRecord::where('student_id', $childId)->count(),
            ],
            'recent_quizzes' => $recentQuizzes,
            'weekly_activity' => $weeklyActivity
        ]);
    }
}
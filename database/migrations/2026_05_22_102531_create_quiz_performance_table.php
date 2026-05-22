<?php

/**
 * PERFORMANCE TRACKING & MONTHLY REPORTS
 * 
 * This system tracks:
 * - Which topics students struggle with
 * - Which questions they get wrong most
 * - Overall performance trends
 * - Generates monthly reports for parents
 */

// ============================================
// MIGRATION: Create quiz_performance table
// ============================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quiz_performance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('lesson_id'); // The quiz lesson
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('subject_id');
            
            // Performance data
            $table->integer('score'); // Percentage
            $table->integer('total_questions');
            $table->integer('correct_answers');
            $table->integer('wrong_answers');
            $table->json('wrong_question_ids'); // Track which questions failed
            $table->boolean('passed');
            $table->integer('time_taken_seconds')->nullable();
            
            // For monthly reports
            $table->timestamp('completed_at');
            $table->integer('attempt_number')->default(1);
            
            $table->timestamps();
            
            // Indexes
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('external_lessons')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('external_topics')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('external_subjects')->onDelete('cascade');
            
            $table->index(['student_id', 'completed_at']);
            $table->index(['topic_id', 'student_id']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('quiz_performance');
    }
};


// ============================================
// CONTROLLER: Update quiz submission
// ============================================

// In your ExternalLessonController.php, update the submitQuiz method:

public function submitQuiz(Request $request, $lessonId)
{
    $student = auth()->user();
    $lesson = DB::table('external_lessons')->find($lessonId);
    $quizData = json_decode($lesson->quiz_data, true);
    
    // Calculate score
    $totalQuestions = count($quizData['questions']);
    $correctAnswers = 0;
    $wrongQuestionIds = [];
    
    foreach ($quizData['questions'] as $index => $question) {
        $userAnswer = $request->answers["q" . ($index + 1)] ?? null;
        if ($userAnswer === $question['correct_answer']) {
            $correctAnswers++;
        } else {
            $wrongQuestionIds[] = $index + 1;
        }
    }
    
    $score = round(($correctAnswers / $totalQuestions) * 100);
    $passed = $score >= ($quizData['pass_percentage'] ?? 70);
    
    // SAVE PERFORMANCE DATA
    DB::table('quiz_performance')->insert([
        'student_id' => $student->id,
        'lesson_id' => $lessonId,
        'topic_id' => $lesson->topic_id,
        'subject_id' => DB::table('external_topics')->where('id', $lesson->topic_id)->value('subject_id'),
        'score' => $score,
        'total_questions' => $totalQuestions,
        'correct_answers' => $correctAnswers,
        'wrong_answers' => $totalQuestions - $correctAnswers,
        'wrong_question_ids' => json_encode($wrongQuestionIds),
        'passed' => $passed,
        'completed_at' => now(),
        'attempt_number' => DB::table('quiz_performance')
            ->where('student_id', $student->id)
            ->where('lesson_id', $lessonId)
            ->count() + 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    return response()->json([
        'score' => $score,
        'correct_answers' => $correctAnswers,
        'total_questions' => $totalQuestions,
        'passed' => $passed,
        'message' => $passed ? 'Great job!' : 'Keep practicing!'
    ]);
}


// ============================================
// ANALYTICS: Monthly Report Generator
// ============================================

class MonthlyReportController extends Controller
{
    /**
     * Generate monthly performance report for a student
     */
    public function generateMonthlyReport($studentId, $month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        // Get all quiz performances for the month
        $performances = DB::table('quiz_performance as qp')
            ->join('external_topics as t', 'qp.topic_id', '=', 't.id')
            ->join('external_subjects as s', 'qp.subject_id', '=', 's.id')
            ->where('qp.student_id', $studentId)
            ->whereBetween('qp.completed_at', [$startDate, $endDate])
            ->select(
                's.name as subject',
                't.title as topic',
                'qp.score',
                'qp.passed',
                'qp.wrong_question_ids',
                'qp.completed_at'
            )
            ->get();
        
        // Calculate statistics
        $totalQuizzes = $performances->count();
        $passedQuizzes = $performances->where('passed', true)->count();
        $averageScore = $performances->avg('score');
        
        // Identify weak areas (topics with < 70% average)
        $weakAreas = $performances->groupBy('topic')->map(function ($group) {
            return [
                'topic' => $group->first()->topic,
                'subject' => $group->first()->subject,
                'average_score' => round($group->avg('score')),
                'attempts' => $group->count(),
            ];
        })->where('average_score', '<', 70)->values();
        
        // Identify strong areas (topics with >= 85% average)
        $strongAreas = $performances->groupBy('topic')->map(function ($group) {
            return [
                'topic' => $group->first()->topic,
                'subject' => $group->first()->subject,
                'average_score' => round($group->avg('score')),
                'attempts' => $group->count(),
            ];
        })->where('average_score', '>=', 85)->values();
        
        // Generate recommendations
        $recommendations = [];
        if ($weakAreas->count() > 0) {
            $recommendations[] = "Consider 1-on-1 tutoring sessions for: " . 
                $weakAreas->pluck('topic')->implode(', ');
        }
        if ($averageScore >= 85) {
            $recommendations[] = "Excellent progress! Student is ready for more advanced topics.";
        } elseif ($averageScore < 60) {
            $recommendations[] = "Student may benefit from additional practice and support.";
        }
        
        return response()->json([
            'student_id' => $studentId,
            'month' => $startDate->format('F Y'),
            'overview' => [
                'total_quizzes' => $totalQuizzes,
                'passed_quizzes' => $passedQuizzes,
                'pass_rate' => $totalQuizzes > 0 ? round(($passedQuizzes / $totalQuizzes) * 100) : 0,
                'average_score' => round($averageScore),
            ],
            'strong_areas' => $strongAreas,
            'weak_areas' => $weakAreas,
            'recommendations' => $recommendations,
            'needs_tutoring' => $weakAreas->count() > 0,
        ]);
    }
    
    /**
     * Send monthly report email to parent
     */
    public function emailMonthlyReport($studentId)
    {
        $report = $this->generateMonthlyReport($studentId);
        
        $student = DB::table('users')->find($studentId);
        $parent = DB::table('users')->find($student->parent_id);
        
        // Send email to parent
        Mail::to($parent->email)->send(new MonthlyPerformanceReport($student, $report));
        
        return response()->json(['message' => 'Report sent to parent']);
    }
}


// ============================================
// ROUTE: Add these to api.php
// ============================================

// Monthly reports
Route::get('/students/{id}/monthly-report', [MonthlyReportController::class, 'generateMonthlyReport']);
Route::post('/students/{id}/email-report', [MonthlyReportController::class, 'emailMonthlyReport']);

// Analytics dashboard for parents
Route::get('/students/{id}/analytics', function ($studentId) {
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
        'message' => $weakTopics->count() > 0 
            ? 'Consider booking 1-on-1 tutoring for these topics'
            : 'Great progress! No weak areas detected.'
    ]);
});
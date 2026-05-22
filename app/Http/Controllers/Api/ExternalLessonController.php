<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExternalLesson;
use App\Models\ExternalTopic;
use App\Models\UserExternalLessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalLessonController extends Controller
{
    /**
     * Get all lessons for a topic
     */
    public function indexByTopic($topicId)
    {
        $topic = ExternalTopic::with('lessons')->findOrFail($topicId);
        
        return response()->json([
            'success' => true,
            'topic' => $topic
        ]);
    }

    /**
     * Get single lesson with quiz data
     */
    public function show($id)
    {
        $lesson = ExternalLesson::with('topic.subject')->findOrFail($id);
        $user = auth()->user();

        $progress = UserExternalLessonProgress::where('user_id', $user->id)
                                              ->where('lesson_id', $id)
                                              ->first();

        return response()->json([
            'success' => true,
            'lesson' => $lesson,
            'progress' => $progress
        ]);
    }

    /**
     * Update lesson progress (video watched, status)
     */
    public function updateProgress(Request $request, $id)
    {
        $user = auth()->user();
        
        $progress = UserExternalLessonProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'lesson_id' => $id
            ],
            [
                'status' => $request->status ?? 'in_progress',
                'video_watched' => $request->video_watched ?? false,
                'started_at' => $request->status == 'in_progress' ? now() : null
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress updated',
            'progress' => $progress
        ]);
    }

    /**
     * Submit quiz and calculate score
     */
    
// ============================================
// CONTROLLER: Update quiz submission
// ============================================



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

}
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
    public function submitQuiz(Request $request, $id)
    {
        $user = auth()->user();
        $lesson = ExternalLesson::findOrFail($id);
        
        $userAnswers = $request->answers;
        $quizData = $lesson->quiz_data;
        
        if (!$quizData || !isset($quizData['questions'])) {
            return response()->json([
                'success' => false,
                'message' => 'No quiz data available'
            ], 400);
        }

        $correctCount = 0;
        $totalQuestions = count($quizData['questions']);
        $results = [];
        
        foreach ($quizData['questions'] as $index => $question) {
            $questionKey = 'q' . ($index + 1);
            $userAnswer = $userAnswers[$questionKey] ?? null;
            $isCorrect = $userAnswer == $question['correct_answer'];
            
            if ($isCorrect) {
                $correctCount++;
            }
            
            $results[] = [
                'question' => $question['question'],
                'user_answer' => $userAnswer,
                'correct_answer' => $question['correct_answer'],
                'is_correct' => $isCorrect,
                'explanation' => $question['explanation']
            ];
        }
        
        $score = round(($correctCount / $totalQuestions) * 100);
        $passed = $score >= 70;
        
        $progress = UserExternalLessonProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'lesson_id' => $id
            ],
            [
                'quiz_score' => $score,
                'quiz_attempts' => DB::raw('quiz_attempts + 1'),
                'status' => $passed ? 'completed' : 'in_progress',
                'completed_at' => $passed ? now() : null
            ]
        );

        return response()->json([
            'success' => true,
            'score' => $score,
            'passed' => $passed,
            'correct_answers' => $correctCount,
            'total_questions' => $totalQuestions,
            'results' => $results,
            'progress' => $progress
        ]);
    }
}
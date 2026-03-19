<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    // Fetch quiz details with questions
    public function show($id)
    {
        $quiz = Quiz::with('questions.answerOptions')->findOrFail($id);
        return response()->json($quiz);
    }

    // Start a new quiz attempt
    public function startAttempt(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);
        $student = $request->user();

        // Calculate attempt number
        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->count();

        if ($attemptCount >= $quiz->max_attempts) {
            return response()->json(['message' => 'Maximum attempts reached'], 403);
        }

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now(),
            'attempt_number' => $attemptCount + 1,
        ]);

        return response()->json($attempt, 201);
    }

    // Submit quiz answers
    public function submitAttempt(Request $request, $id)
    {
        $attempt = QuizAttempt::findOrFail($id);
        $answers = $request->input('answers'); // Array of question_id => selected_option_id/text
        
        // Logical processing for grading would go here
        // We will integrate the AIService here later for pronunciation assessment
        
        $attempt->update([
            'completed_at' => now(),
            'score' => $request->input('score', 0),
            'passed' => $request->input('score', 0) >= $attempt->quiz->passing_score,
        ]);

        return response()->json(['message' => 'Quiz submitted', 'attempt' => $attempt]);
    }
}
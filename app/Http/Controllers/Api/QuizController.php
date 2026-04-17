<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function show($id)
    {
        $quiz = Quiz::with('questions.answerOptions')->findOrFail($id);
        return response()->json($quiz);
    }

    public function startAttempt(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);
        $student = $request->user();

        // Check if student has already passed this quiz
        $hasPassed = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('passed', true)
            ->exists();

        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->count();

        // Allow unlimited practice if already passed, otherwise check max_attempts
        if (!$hasPassed && $attemptCount >= $quiz->max_attempts) {
            return response()->json(['message' => 'Maximum attempts reached'], 403);
        }

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now(),
            'attempt_number' => $attemptCount + 1,
            'is_practice' => $hasPassed // 💡 Flag to indicate this won't award new coins
        ]);

        return response()->json($attempt, 201);
    }

    public function submitAttempt(Request $request, $id)
    {
        $attempt = QuizAttempt::with('quiz')->findOrFail($id);
        $student = $request->user();
        
        $score = $request->input('score', 0);
        $passingScore = $attempt->quiz->passing_score ?? 70;
        $isPassing = $score >= $passingScore;

        // 1. Check if this is a repeat pass
        $alreadyPassed = QuizAttempt::where('quiz_id', $attempt->quiz_id)
            ->where('student_id', $student->id)
            ->where('passed', true)
            ->where('id', '!=', $id)
            ->exists();

        $attempt->update([
            'completed_at' => now(),
            'score' => $score,
            'passed' => $isPassing,
        ]);

        // 2. Logic for FricaCoin Awards (Only on first pass)
        if ($isPassing && !$alreadyPassed) {
            // Logic to award FricaCoins and Points to the student profile
            $points = $score * 5; 
            $student->studentProfile->increment('total_points', $points);
            $student->studentProfile->increment('total_coins', 10); // Standard award
        }

        return response()->json([
            'message' => $isPassing ? 'Ẹ kú iṣẹ́! You passed!' : 'Ó tọ́ díẹ̀. Keep practicing!',
            'passed' => $isPassing,
            'already_passed' => $alreadyPassed,
            'score' => $score,
            'attempt' => $attempt
        ]);
    }
}
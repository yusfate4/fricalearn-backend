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
    return DB::transaction(function () use ($request, $id) {
        // 1. Load the attempt with quiz relationship
        $attempt = QuizAttempt::with('quiz')->findOrFail($id);
        $student = $request->user();
        
        // Ensure profile exists to avoid errors
        $profile = $student->studentProfile;
        if (!$profile) {
            return response()->json(['error' => 'Student profile not found'], 404);
        }

        $score = $request->input('score', 0);
        $passingScore = $attempt->quiz->passing_score ?? 70; 
        $isPassing = $score >= $passingScore;

        // 🕵️ CRITICAL CHECK: Lock the table for this query to prevent race conditions
        $hasPassHistory = QuizAttempt::where('quiz_id', $attempt->quiz_id)
            ->where('student_id', $student->id)
            ->where('passed', true)
            ->where('id', '!=', $id) 
            ->exists();

        // 2. Update the current attempt status
        $attempt->update([
            'completed_at' => now(),
            'score' => $score,
            'passed' => $isPassing,
        ]);

        $coinsEarned = 0;
        $pointsEarned = 0;
        $isFirstTimePass = false;

        // 🏆 3. Reward logic: STRICTLY only if passing AND NO history
        if ($isPassing && !$hasPassHistory) {
            $isFirstTimePass = true;
            $pointsEarned = $score * 5;
            $coinsEarned = 10; 

            // Increment and force a fresh data fetch from DB
            $profile->increment('total_points', $pointsEarned);
            $profile->increment('total_coins', $coinsEarned);
            $profile->refresh(); 
            
            $message = "Ẹ kú iṣẹ́! You earned $coinsEarned FricaCoins!";
        } elseif ($isPassing && $hasPassHistory) {
            $message = "Ẹ dúpẹ́! Great practice, but you've already claimed the coins for this week.";
        } else {
            $message = "Ó tọ́ díẹ̀. Review Oluko's notes and try again!";
        }

        return response()->json([
            'passed' => $isPassing,
            'score' => $score,
            'message' => $message,
            'coins_earned' => $coinsEarned,
            'is_practice' => $hasPassHistory,
            'total_coins_now' => $profile->total_coins // Send this back so React can update the UI
        ]);
    });
}
}
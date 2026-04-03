<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\Quiz; // 🚀 THIS WAS MISSING
use Carbon\Carbon;

class QuizAttemptSeeder extends Seeder
{
    public function run()
    {
        // 1. Find a student (Ayo)
        $student = User::where('role', 'student')->first();
        
        // 2. Find a quiz
        $quiz = Quiz::first();

        if ($student && $quiz) {
            // Create a 100% score from yesterday
            QuizAttempt::create([
                'student_id' => $student->id,
                'quiz_id' => $quiz->id,
                'score' => 100.00,
                'passed' => true,
                'time_taken_seconds' => 120,
                'completed_at' => Carbon::now()->subDay(),
            ]);

            // Create a 90% score from today
            QuizAttempt::create([
                'student_id' => $student->id,
                'quiz_id' => $quiz->id,
                'score' => 90.00,
                'passed' => true,
                'time_taken_seconds' => 150,
                'completed_at' => Carbon::now(),
            ]);

            $this->command->info('Successfully added test scores for ' . $student->name);
        } else {
            $this->command->error('Could not find a student or a quiz to seed.');
        }
    }
}
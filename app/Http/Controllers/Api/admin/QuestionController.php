<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function store(Request $request)
    {
        // 1. Only Yusuf (Admin) can add questions
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Validate the quiz data (Now including the fail-safe fields!)
        $validated = $request->validate([
            'lesson_id'             => 'required|exists:lessons,id',
            'question_text'         => 'required|string',
            'option_a'              => 'required|string',
            'option_b'              => 'required|string',
            'option_c'              => 'required|string',
            'correct_answer'        => 'required|in:a,b,c',
            
            // 🚨 Tell Laravel to accept and save these new fields
            'explanation_video_url' => 'nullable|string',
            'explanation_text'      => 'nullable|string',
        ]);

        // 3. Save to the 'questions' table
        $question = Question::create($validated);

        return response()->json([
            'message' => 'Question added successfully!',
            'question' => $question
        ], 201);
    }
}
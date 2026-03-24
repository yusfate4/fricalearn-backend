<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\Request;

class AIHintController extends Controller
{
    public function getHint(Request $request, AIService $ai)
    {
        $request->validate(['question_text' => 'required|string']);

        $systemPrompt = "You are a kind Yoruba and Math tutor. Provide a short, 1-sentence hint for the question provided. DO NOT give the answer. Use encouraging language like 'Try thinking about...' or 'Remember that...'";
        
        $hint = $ai->askAI($systemPrompt, "Give me a hint for this question: " . $request->question_text);

        return response()->json(['hint' => $hint]);
    }
}
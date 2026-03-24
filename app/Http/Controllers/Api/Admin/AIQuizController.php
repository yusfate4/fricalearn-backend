<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use App\Models\Lesson;
use Illuminate\Http\Request;

class AIQuizController extends Controller
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'count' => 'integer|min:1|max:10',
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);
        $count = $validated['count'] ?? 5;

        $systemPrompt = "You are a Yoruba and Math tutor for kids aged 5-12. Generate multiple-choice questions. Return ONLY a JSON object with a 'questions' key.";

        $userPrompt = "Generate {$count} questions from this lesson: '{$lesson->content}'. Each question needs 'question_text', 'explanation', and 'options' (array of 'option_text' and 'is_correct').";

        $rawResponse = $this->aiService->askAI($systemPrompt, $userPrompt);

        if (!$rawResponse) {
            return response()->json(['message' => 'AI generation failed'], 500);
        }

        // Clean up markdown markers if AI adds them
        $cleanJson = str_replace(['```json', '```'], '', $rawResponse);
        $data = json_decode($cleanJson, true);

        return response()->json([
            'success' => true,
            'data' => $data['questions'] ?? []
        ]);
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $apiKey;

    public function __construct()
    {
        // Pulls sk-xxxx from your .env file
        $this->apiKey = config('services.openai.key');
    }

    /**
     * 🤖 Send a prompt to OpenAI and get a string back
     */
    public function askAI(string $systemPrompt, string $userPrompt)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // 👈 Best for MVP cost-saving
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::error('AI Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('AI Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
 * 🎙️ AI Pronunciation Scorer
 */
public function scorePronunciation(string $expectedText, string $transcribedText)
{
    $systemPrompt = "You are a Yoruba language expert. Compare the 'Expected' word with the 'Actual' transcription from a student. 
    Calculate a match percentage (0-100) based on phonetics and accuracy. 
    Return ONLY JSON: {\"score\": 85, \"feedback\": \"Great job, but watch the tone on the last syllable!\"}";

    $userPrompt = "Expected: '{$expectedText}', Actual: '{$transcribedText}'";

    return $this->askAI($systemPrompt, $userPrompt);
}
}
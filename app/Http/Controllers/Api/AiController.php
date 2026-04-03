<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\MasterSchedule;

class AiController extends Controller
{
    /**
     * Chat with Olu - The AI Yoruba Tutor
     * Handles conversational AI with short-term memory (history).
     */
    public function chatWithOlu(Request $request)
    {
        $userMessage = $request->input('message');
        $history = $request->input('history', []); 

        $apiKey = env('OPENAI_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'reply' => "Olu is sleeping right now (Missing API Key). Please tell the Admin! 😴"
            ], 500);
        }

        $client = OpenAI::client($apiKey);

        // 🎙️ THE OLU PERSONALITY PROMPT
        $systemPrompt = "You are Olu, a friendly Yoruba language tutor for kids in the diaspora. 
        Your tone is energetic, encouraging, and fun. Use emojis like 🌟, 🐢, and 🇳🇬.
        Rules:
        1. Speak primarily in Yoruba but ALWAYS provide English translations in parentheses immediately after.
        2. Keep sentences short and simple for a child (Ayo) to understand.
        3. If the user makes a grammar mistake in Yoruba, gently correct them and encourage them.
        4. Focus on conversational Yoruba (greetings, food, family, animals).
        5. NEVER break character. You are a real tutor, not an AI.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Keep context manageable by taking the last 4 exchanges
        foreach (array_slice($history, -4) as $chat) {
            $messages[] = [
                'role' => $chat['role'], 
                'content' => $chat['content']
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 200,
                'temperature' => 0.7,
            ]);

            $reply = $response->choices[0]->message->content;

            return response()->json([
                'reply' => $reply,
                'status' => 'success'
            ]);

        } catch (\Exception $e) {
            Log::error("Olu Chat Error: " . $e->getMessage());
            return response()->json([
                'reply' => "E dárí jìn mí (Forgive me), I got a bit confused. Can you say that again?",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify Pronunciation
     * Uses OpenAI Whisper to transcribe audio and compare against expected text.
     */
    public function verifyPronunciation(Request $request)
    {
        // 1. Initial Validation
        if (!$request->hasFile('audio')) {
            return response()->json(['error' => 'No audio file provided'], 400);
        }

        $expected = $request->input('expected_text', '');
        $apiKey = env('OPENAI_API_KEY');

        if (!$apiKey) {
            return response()->json(['error' => 'AI Configuration missing on server'], 500);
        }

       try {
    $client = OpenAI::client($apiKey);
    $file = $request->file('audio');

    // 🚀 THE STABLE FIX: Open the file directly from its temporary path
    $response = retry(2, function () use ($client, $file) {
        return $client->audio()->transcribe([
            'model' => 'whisper-1',
            // Pass the stream directly using the temporary real path
            'file' => fopen($file->getRealPath(), 'r'),
        ]);
    }, 500);

    $transcript = trim($response->text);

            /**
             * 🎙️ 2. Transcribe using Whisper
             * We wrap this in a retry() to handle the 'Rate Limit' or 'Service Busy' blips.
             */
            $response = retry(2, function () use ($client, $file) {
                return $client->audio()->transcribe([
                    'model' => 'whisper-1',
                    'file' => fopen($file->getRealPath(), 'r'),
                ]);
            }, 500); // Wait 500ms between attempts

            $transcript = trim($response->text);

            /**
             * 🚀 3. THE SILENCE GUARD
             * If the transcription is empty, the student likely didn't speak.
             */
            if (empty($transcript)) {
                return response()->json([
                    'score' => 0,
                    'feedback' => "Olu didn't hear anything! 👂 Please try speaking clearly.",
                    'coins_earned' => 0
                ]);
            }

            /**
             * 🧠 4. Comparison Logic
             * Strip punctuation and lowercase both strings for a fair comparison.
             */
            $cleanTranscript = strtolower(preg_replace('/[^\w\s]/', '', $transcript));
            $cleanExpected = strtolower(preg_replace('/[^\w\s]/', '', $expected));

            similar_text($cleanExpected, $cleanTranscript, $percent);
            $score = round($percent);

            // Safety check: Don't give high scores to single-letter background noise
            if (strlen($cleanTranscript) < 2 && strlen($cleanExpected) > 3) {
                $score = min($score, 10);
            }

            // Determine Feedback based on accuracy
            $feedback = "Almost there! Keep practicing. 👍";
            if ($score >= 90) {
                $feedback = "Ẹ kú iṣẹ́! Perfect pronunciation! 🌟";
            } elseif ($score >= 75) {
                $feedback = "Great job! You're sounding like a pro. 👍";
            }

            return response()->json([
                'score' => $score,
                'feedback' => $feedback,
                'heard' => $transcript,
                'coins_earned' => $score >= 80 ? 20 : 0
            ]);

        } catch (\Exception $e) {
            Log::error("Whisper Pronunciation Error: " . $e->getMessage());
            
            return response()->json([
                'error' => 'AI Service Error',
                'message' => $e->getMessage() 
            ], 500);
        }
    }

    /**
     * Get the global active class schedule for the sidebar/timer.
     */
    public function getActiveSchedule()
    {
        $schedule = MasterSchedule::where('is_active', true)->first();

        if (!$schedule) {
            return response()->json(['start_time' => '12:00'], 200); 
        }

        return response()->json([
            'day' => $schedule->day_of_week,
            'start_time' => date('H:i', strtotime($schedule->start_time_wat))
        ]);
    }

    /**
     * Update the global class schedule (Admin Only).
     */
    public function updateSchedule(Request $request)
    {
        $request->validate([
            'start_time_wat' => 'required',
            'day_of_week'    => 'required'
        ]);

        $schedule = MasterSchedule::updateOrCreate(
            ['id' => 1], // Consistently update the primary record
            [
                'day_of_week' => $request->day_of_week,
                'start_time_wat' => $request->start_time_wat,
                'is_active' => true
            ]
        );

        return response()->json(['message' => 'Global Schedule updated!']);
    }
}
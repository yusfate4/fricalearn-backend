<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\MasterSchedule;
use App\Models\User;

class AiController extends Controller
{
    /**
     * Chat with Oluko - The AI Heritage Tutor
     * Handles focused conversational AI with session time limits.
     */
    public function chatWithOlu(Request $request)
    {
        $user = $request->user();
        $userMessage = $request->input('message');
        $history = $request->input('history', []); 

        // 🛡️ 1. TIMEBOX GUARDRAIL (Item 10)
        // Max 120 minutes per day. We assume each message exchange represents ~1 minute of engagement.
        $profile = $user->studentProfile;
        if ($profile && $profile->daily_ai_minutes >= 120) {
            return response()->json([
                'reply' => "Oluko is resting now to prepare for our next adventure! 🌙 You've done amazing work today. See you tomorrow!",
                'status' => 'limit_reached'
            ], 403);
        }

        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return response()->json(['reply' => "Oluko is sleeping (API Key Missing). 😴"], 500);
        }

        $client = OpenAI::client($apiKey);

        // 🧠 2. DYNAMIC IDENTITY & FOCUS (Items 5, 7, 11)
        $studentName = $user->name ?? 'Ayo';
        $language = $profile->learning_language ?? 'Yoruba';

      // 🎙️ THE STRENGTHENED OLỤKỌ GUARDRAIL PROMPT
        $systemPrompt = "You are 'Olụkọ', the Heritage Language Teacher for FricaLearn. 

        STRICT OPERATING PROCEDURES:
        1. MISSION: Your only job is to teach {$language}. 
        2. OFF-LIMITS TOPICS: Football (Arsenal, Chelsea, Barca, Ronaldo, etc.), Video Games, Movies, and Pop Culture are STICKY TRAPS. 
        3. THE SHUT-DOWN RULE: If {$studentName} mentions a sticky trap topic, you MUST say: 
        'That is interesting! But as your Olụkọ, I am here to help you master {$language}. We must stay focused on our lesson. Let\'s go back to [Topic]!'
        4. DO NOT translate off-topic sentences. (e.g., Do NOT teach them how to say 'I love Arsenal' in Yoruba). This encourages them to keep talking about football.
        5. NO FOLLOW-UP QUESTIONS: Never ask the student about their favorite player, team, or position. 

        PEDAGOGY:
        - Speak primarily in {$language} with English translations.
        - If {$studentName} is distracted, gently but firmly lead them back to Greetings, Family, or Numbers.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (array_slice($history, -4) as $chat) {
            $messages[] = ['role' => $chat['role'], 'content' => $chat['content']];
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

            // 📈 3. INCREMENT USAGE (Item 10)
            if ($profile) {
                $profile->increment('daily_ai_minutes', 1); 
            }

            return response()->json([
                'reply' => $reply,
                'status' => 'success'
            ]);

        } catch (\Exception $e) {
            Log::error("Oluko Chat Error: " . $e->getMessage());
            return response()->json([
                'reply' => "E dárí jìn mí (Forgive me), I got a bit confused. Let's try again!",
            ], 500);
        }
    }

    /**
     * Verify Pronunciation
     */
    public function verifyPronunciation(Request $request)
    {
        if (!$request->hasFile('audio')) {
            return response()->json(['error' => 'No audio file provided'], 400);
        }

        $expected = $request->input('expected_text', '');
        $apiKey = env('OPENAI_API_KEY');
        $user = $request->user();

        try {
            $client = OpenAI::client($apiKey);
            $file = $request->file('audio');

            $response = retry(2, function () use ($client, $file) {
                return $client->audio()->transcribe([
                    'model' => 'whisper-1',
                    'file' => fopen($file->getRealPath(), 'r'),
                ]);
            }, 500);

            $transcript = trim($response->text);

            if (empty($transcript)) {
                return response()->json([
                    'score' => 0,
                    'feedback' => "Oluko didn't hear anything! 👂 Please speak clearly.",
                    'coins_earned' => 0
                ]);
            }

            $cleanTranscript = strtolower(preg_replace('/[^\w\s]/', '', $transcript));
            $cleanExpected = strtolower(preg_replace('/[^\w\s]/', '', $expected));

            similar_text($cleanExpected, $cleanTranscript, $percent);
            $score = round($percent);

            // 🎯 Point Adjustment (Item 5 logic: Scale to 5-point chunks)
            $points = ($score >= 80) ? 5 : 0; 

            $feedback = "Keep practicing! 👍";
            if ($score >= 90) {
                $feedback = "Ẹ kú iṣẹ́! Perfect! 🌟";
            } elseif ($score >= 70) {
                $feedback = "Great effort! 👍";
            }

            return response()->json([
                'score' => $score,
                'feedback' => $feedback,
                'heard' => $transcript,
                'points_earned' => $points
            ]);

        } catch (\Exception $e) {
            Log::error("Whisper Error: " . $e->getMessage());
            return response()->json(['error' => 'AI Service Error'], 500);
        }
    }

    /**
     * Get the global active class schedule (Nigeria Time - Item 4).
     */
    public function getActiveSchedule()
    {
        // 🌍 Logic: Hardcoded to Africa/Lagos
        date_default_timezone_set('Africa/Lagos');
        
        $schedule = MasterSchedule::where('is_active', true)->first();

        if (!$schedule) {
            return response()->json(['start_time' => '12:00', 'timezone' => 'WAT'], 200); 
        }

        return response()->json([
            'day' => $schedule->day_of_week,
            'start_time' => date('H:i', strtotime($schedule->start_time_wat)),
            'label' => 'Nigeria Time (WAT)'
        ]);
    }

    public function updateSchedule(Request $request)
    {
        $request->validate([
            'start_time_wat' => 'required',
            'day_of_week'    => 'required'
        ]);

        $schedule = MasterSchedule::updateOrCreate(
            ['id' => 1],
            [
                'day_of_week' => $request->day_of_week,
                'start_time_wat' => $request->start_time_wat,
                'is_active' => true
            ]
        );

        return response()->json(['message' => 'Schedule updated to Nigeria Time!']);
    }
}
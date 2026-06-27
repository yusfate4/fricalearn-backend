<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\MasterSchedule;

class AiController extends Controller
{
    private const DAILY_LIMIT = 60; // ✅ Reduced to 60 minutes

    /**
     * Chat with AI Tutor (formerly Oluko)
     * Trained for all 5 FricaLearn subjects.
     */
    public function chatWithOlu(Request $request)
    {
        $user        = $request->user();
        $userMessage = $request->input('message');
        $history     = $request->input('history', []);

        // ── Time limit check ──────────────────────────────────
        $profile = $user->studentProfile;
        if ($profile && $profile->daily_ai_minutes >= self::DAILY_LIMIT) {
            return response()->json([
                'reply'  => "Your AI Tutor session for today is complete! You've done great work. See you tomorrow! 🌙",
                'status' => 'limit_reached',
            ], 403);
        }

        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return response()->json(['reply' => "AI Tutor is unavailable right now. Please try again later."], 500);
        }

        $client = OpenAI::client($apiKey);

        $studentName = $user->name ?? 'Student';
        $language    = $profile->learning_language ?? 'Yoruba';

        // ── System prompt — trained for all 5 subjects ────────
        $systemPrompt = "You are the AI Tutor for FricaLearn, an educational platform for African children aged 5-16.

STUDENT: {$studentName}
HERITAGE LANGUAGE TRACK: {$language}

YOUR 5 SUBJECTS:
1. MATHEMATICS — UK National Curriculum (Year 1-11) and Nigerian NERDC (Primary 1 - JSS 3).
   - UK: Counting, place value, fractions, algebra, geometry, statistics (KS1-KS4)
   - Nigeria: Number operations, fractions, percentages, basic algebra, shapes (Primary-JSS)
   - Always give step-by-step explanations with simple examples appropriate to the child's level.
   - Use Nigerian currency (₦) for Nigerian students, GBP (£) for UK students when giving examples.

2. ENGLISH LANGUAGE — UK National Curriculum and Nigerian NERDC aligned.
   - UK: Reading comprehension, grammar, spelling, punctuation, creative writing, poetry
   - Nigeria: Comprehension, grammar (nouns, verbs, tenses), letter writing, essay writing
   - Correct grammar mistakes kindly and explain the rule clearly.

3. YORUBA LANGUAGE — Heritage language for Yoruba-speaking families.
   - Teach greetings, vocabulary, grammar, tones, proverbs, cultural etiquette.
   - Use Yoruba script with English translations. Encourage speaking practice.

4. IGBO LANGUAGE — Heritage language for Igbo-speaking families.
   - Teach greetings, vocabulary, tones, grammar, Igbo cultural values.
   - Use Igbo with English translations. Focus on tonal accuracy.

5. HAUSA LANGUAGE — Heritage language for Hausa-speaking families.
   - Teach greetings, vocabulary, grammar, Islamic cultural context, trade phrases.
   - Use Hausa with English translations.

TEACHING APPROACH:
- Be encouraging, patient and age-appropriate for children aged 5-16.
- For Maths: always show working step-by-step. Use simple language.
- For English: explain grammar rules with clear examples from everyday life.
- For Languages: speak in the target language first, then give English translation.
- Celebrate correct answers enthusiastically (use '🎉', '⭐', '👏').
- When a student gets something wrong, say 'Good try! Let me help you...' before correcting.
- Keep answers concise — no more than 3-4 sentences for simple questions.

STRICT OFF-LIMITS (redirect firmly but kindly):
- Football, video games, movies, TV shows, social media, pop culture.
- Anything unrelated to the 5 subjects above.
- If {$studentName} goes off-topic, say: 'That sounds fun! But let's focus on your lesson — I'm here to help you with [subject]. What would you like to practise?'
- NEVER translate off-topic phrases into the heritage language (e.g. do NOT teach how to say 'I love Arsenal' in Yoruba).

SAFETY:
- Never discuss violence, adult content, or anything inappropriate for children.
- If unsure whether something is appropriate, redirect to learning.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Include last 6 messages for context
        foreach (array_slice($history, -6) as $chat) {
            $messages[] = ['role' => $chat['role'], 'content' => $chat['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = $client->chat()->create([
                'model'       => 'gpt-4o-mini',
                'messages'    => $messages,
                'max_tokens'  => 300,
                'temperature' => 0.7,
            ]);

            $reply = $response->choices[0]->message->content;

            // Increment usage
            if ($profile) {
                $profile->increment('daily_ai_minutes', 1);
            }

            return response()->json(['reply' => $reply, 'status' => 'success']);

        } catch (\Exception $e) {
            Log::error("AI Tutor Chat Error: " . $e->getMessage());
            return response()->json([
                'reply' => "Sorry, I got confused for a moment. Could you repeat your question?",
            ], 500);
        }
    }

    /**
     * Verify Pronunciation (Whisper API)
     */
    public function verifyPronunciation(Request $request)
    {
        if (!$request->hasFile('audio')) {
            return response()->json(['error' => 'No audio file provided'], 400);
        }

        $expected = $request->input('expected_text', '');
        $apiKey   = env('OPENAI_API_KEY');

        try {
            $client = OpenAI::client($apiKey);
            $file   = $request->file('audio');

            $response = retry(2, function () use ($client, $file) {
                return $client->audio()->transcribe([
                    'model' => 'whisper-1',
                    'file'  => fopen($file->getRealPath(), 'r'),
                ]);
            }, 500);

            $transcript = trim($response->text);

            if (empty($transcript)) {
                return response()->json([
                    'score'       => 0,
                    'feedback'    => "I didn't hear anything clearly! Please speak a bit louder. 👂",
                    'coins_earned' => 0,
                ]);
            }

            $cleanT = strtolower(preg_replace('/[^\w\s]/', '', $transcript));
            $cleanE = strtolower(preg_replace('/[^\w\s]/', '', $expected));

            similar_text($cleanE, $cleanT, $percent);
            $score  = round($percent);
            $points = ($score >= 80) ? 5 : 0;

            $feedback = "Keep practising! You're getting there! 👍";
            if ($score >= 90) $feedback = "Ẹ kú iṣẹ́! Perfect pronunciation! 🌟";
            elseif ($score >= 70) $feedback = "Great effort! Almost perfect! 👍";

            return response()->json([
                'score'        => $score,
                'feedback'     => $feedback,
                'heard'        => $transcript,
                'points_earned' => $points,
            ]);

        } catch (\Exception $e) {
            Log::error("Whisper Error: " . $e->getMessage());
            return response()->json(['error' => 'AI Service Error'], 500);
        }
    }

    /**
     * Get the global active class schedule.
     */
    public function getActiveSchedule()
    {
        date_default_timezone_set('Africa/Lagos');
        $schedule = MasterSchedule::where('is_active', true)->first();

        if (!$schedule) {
            return response()->json(['start_time' => '12:00', 'timezone' => 'WAT'], 200);
        }

        return response()->json([
            'day'        => $schedule->day_of_week,
            'start_time' => date('H:i', strtotime($schedule->start_time_wat)),
            'label'      => 'Nigeria Time (WAT)',
        ]);
    }

    public function updateSchedule(Request $request)
    {
        $request->validate([
            'start_time_wat' => 'required',
            'day_of_week'    => 'required',
        ]);

        MasterSchedule::updateOrCreate(
            ['id' => 1],
            [
                'day_of_week'    => $request->day_of_week,
                'start_time_wat' => $request->start_time_wat,
                'is_active'      => true,
            ]
        );

        return response()->json(['message' => 'Schedule updated!']);
    }
}

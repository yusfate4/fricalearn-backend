<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalLesson;
use App\Models\ExternalTopic;
use App\Models\UserExternalLessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalLessonController extends Controller
{
    private function oakApiUrl(): string { return rtrim(config('services.oak.api_url'), '/'); }
    private function oakApiKey(): string { return config('services.oak.api_key'); }

    // =========================================================
    // GET ALL LESSONS FOR A TOPIC
    // =========================================================

    public function indexByTopic($topicId)
    {
        $topic = ExternalTopic::with(['lessons' => function ($q) {
            $q->orderBy('order_index');
        }])->findOrFail($topicId);
        return response()->json(['success' => true, 'topic' => $topic]);
    }

    // =========================================================
    // GET SINGLE LESSON — lazy fetch if not yet populated
    // =========================================================

public function show(Request $request, $id)
    {
        $lesson = ExternalLesson::with('topic.subject')->findOrFail($id);

        if ($this->needsOakContent($lesson)) {
            $lesson = $this->fetchAndStoreOakContent($lesson);
        }

        $apiKey = $this->oakApiKey();

        // --- Resolve the secure VIDEO URL on the fly ---
        if (!empty($lesson->video_url) && str_contains($lesson->video_url, 'thenational.academy')) {
            try {
                $videoRes = Http::withoutRedirecting()->withHeaders([
                    'Authorization' => "Bearer {$apiKey}", 
                    'Accept' => 'application/json'
                ])->get($lesson->video_url);

                $status = $videoRes->status();

                if ($status >= 300 && $status < 400) {
                    $lesson->video_url = $videoRes->header('Location');
                } elseif ($videoRes->successful()) {
                    $vData = $videoRes->json();
                    $lesson->video_url = $vData['url'] ?? $vData['videoUrl'] ?? $vData['signedUrl'] ?? $vData['streamUrl'] ?? $lesson->video_url;
                }
            } catch (\Exception $e) {
                Log::error("Failed to resolve Oak video URL: " . $e->getMessage());
            }
        }

        // --- NEW: Resolve the secure SLIDE DECK URL on the fly ---
        if (!empty($lesson->slide_url) && str_contains($lesson->slide_url, 'thenational.academy')) {
            try {
                $slideRes = Http::withoutRedirecting()->withHeaders([
                    'Authorization' => "Bearer {$apiKey}", 
                    'Accept' => 'application/json'
                ])->get($lesson->slide_url);

                $status = $slideRes->status();

                // Check if Oak redirects to a signed PDF/Slide file
                if ($status >= 300 && $status < 400) {
                    $lesson->slide_url = $slideRes->header('Location');
                } elseif ($slideRes->successful()) {
                    // Extract from JSON if they return a data object
                    $sData = $slideRes->json();
                    $lesson->slide_url = $sData['url'] ?? $sData['presentationUrl'] ?? $sData['slideDeckUrl'] ?? $lesson->slide_url;
                }
            } catch (\Exception $e) {
                Log::error("Failed to resolve Oak slide URL: " . $e->getMessage());
            }
        }

        // Use student_id if provided (parent impersonating child)
        $studentId = $request->query('student_id') ?: auth()->id();

        $progress = UserExternalLessonProgress::where('user_id', $studentId)
            ->where('lesson_id', $id)
            ->first();

        return response()->json([
            'success'  => true,
            'lesson'   => $lesson,
            'progress' => $progress,
        ]);
    }


    private function needsOakContent(ExternalLesson $lesson): bool
    {
        if (empty($lesson->external_id) || !empty($lesson->description)) return false;
        $subject = optional(optional($lesson->topic)->subject);
        return $subject->source === 'Oak National Academy';
    }

    private function fetchAndStoreOakContent(ExternalLesson $lesson): ExternalLesson
    {
        $slug    = $lesson->external_id;
        $apiUrl  = $this->oakApiUrl();
        $apiKey  = $this->oakApiKey();
        $updates = [];

        Log::info("Oak lazy fetch: {$slug}");

        try {
            // ── Summary ───────────────────────────────────────
            $summaryRes = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}", 'Accept' => 'application/json',
            ])->timeout(20)->get("{$apiUrl}/lessons/{$slug}/summary");

            $metadata = ['outcome' => null, 'key_points' => [], 'keywords' => [], 'misconceptions' => []];

            if ($summaryRes->successful()) {
                $s = $summaryRes->json();
                $metadata['outcome']        = $s['pupilLessonOutcome'] ?? null;
                $metadata['key_points']     = array_values(array_filter(array_map(function($p) { return isset($p['keyLearningPoint']) ? $p['keyLearningPoint'] : null; }, $s['keyLearningPoints'] ?? [])));
                $metadata['keywords']       = array_map(function($kw) { return ['keyword' => isset($kw['keyword']) ? $kw['keyword'] : '', 'description' => isset($kw['description']) ? $kw['description'] : '']; }, $s['lessonKeywords'] ?? []);
                $metadata['misconceptions'] = array_map(function($m) { return ['misconception' => isset($m['misconception']) ? $m['misconception'] : '', 'response' => isset($m['response']) ? $m['response'] : '']; }, $s['misconceptionsAndCommonMistakes'] ?? []);
            }

            $updates['worksheet_url'] = json_encode($metadata);

            // ── Transcript ────────────────────────────────────
            $transcriptRes = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}", 'Accept' => 'application/json',
            ])->timeout(20)->get("{$apiUrl}/lessons/{$slug}/transcript");

            if ($transcriptRes->successful()) {
                $t = ($transcriptRes->json())['transcript'] ?? null;
                $updates['description'] = ($t && strlen(trim($t)) > 30)
                    ? substr(trim($t), 0, 10000)
                    : ($metadata['outcome'] ?? 'fetched');
            } else {
                $updates['description'] = $metadata['outcome'] ?? 'fetched';
            }

            // ── Quiz ──────────────────────────────────────────
            $quizRes = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}", 'Accept' => 'application/json',
            ])->timeout(20)->get("{$apiUrl}/lessons/{$slug}/quiz");

            if ($quizRes->successful()) {
                $questions = $this->normaliseOakQuiz($quizRes->json());
                if (!empty($questions)) $updates['quiz_data'] = json_encode($questions);
            }

            // ── Assets (Video & Slides) ───────────────────────
            $assetsRes = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}", 'Accept' => 'application/json',
            ])->timeout(20)->get("{$apiUrl}/lessons/{$slug}/assets");

            $videoUrlToSave = null;
            $slideUrlToSave = null;

            if ($assetsRes->successful()) {
                $assetsData = $assetsRes->json();
                
                // Safely find the video API url
                if (isset($assetsData['assets']) && is_array($assetsData['assets'])) {
                    foreach ($assetsData['assets'] as $asset) {
                        if (isset($asset['type']) && $asset['type'] === 'video' && isset($asset['url'])) {
                            $videoUrlToSave = $asset['url'];
                        }
                        if (isset($asset['type']) && $asset['type'] === 'slideDeck' && isset($asset['url'])) {
                            $slideUrlToSave = $asset['url'];
                        }
                    }
                } 
                // Fallback for older Oak API formats
                else {
                    $videoUrlToSave = $assetsData['videoUrl'] ?? $assetsData['videoObject']['contentUrl'] ?? null;
                }
            }
            
            // Limit to 255 chars to prevent SQL Data Too Long errors
            $updates['video_url'] = $videoUrlToSave ? substr($videoUrlToSave, 0, 255) : null;
            $updates['slide_url'] = $slideUrlToSave ? substr($slideUrlToSave, 0, 255) : null;

        } catch (\Exception $e) {
            Log::error("Oak fetch error for {$slug}: " . $e->getMessage());
            $updates['description'] = 'fetched';
        }

        DB::table('external_lessons')->where('id', $lesson->id)->update(array_merge($updates, ['updated_at' => now()]));
        return ExternalLesson::with('topic.subject')->find($lesson->id);
    }

    private function normaliseOakQuiz(array $raw): array
    {
        $questions = [];
        $source    = !empty($raw['exitQuiz']) ? $raw['exitQuiz'] : ($raw['starterQuiz'] ?? []);

        foreach ($source as $q) {
            $questionText = $q['question'] ?? null;
            if (!$questionText || !empty($q['questionImage'])) continue;

            $options = []; $correctAnswer = null;

            foreach ($q['answers'] ?? [] as $answer) {
                $content = $answer['content'] ?? null;
                if (empty($content) || !is_string($content)) continue;
                if (strlen(trim($content)) === 1 && ctype_alpha($content)) continue;
                $options[] = $content;
                if (($answer['distractor'] ?? true) === false) $correctAnswer = $content;
            }

            if (count($options) < 2 || !$correctAnswer || !in_array($correctAnswer, $options)) continue;

            $questions[] = [
                'question'       => $questionText,
                'options'        => $options,
                'correct_answer' => $correctAnswer,
                'correct_index'  => array_search($correctAnswer, $options),
                'explanation'    => null,
            ];
        }

        return $questions;
    }

    // =========================================================
    // UPDATE PROGRESS
    // =========================================================

    public function updateProgress(Request $request, $id)
    {
        $user = auth()->user();
        $progress = UserExternalLessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $id],
            [
                'status'        => $request->status ?? 'in_progress',
                'video_watched' => $request->video_watched ?? false,
                'started_at'    => $request->status === 'in_progress' ? now() : null,
            ]
        );
        return response()->json(['success' => true, 'progress' => $progress]);
    }

    // =========================================================
    // SUBMIT QUIZ
    // =========================================================

    public function submitQuiz(Request $request, $lessonId)
    {
        $student  = auth()->user();
        $lesson   = DB::table('external_lessons')->find($lessonId);
        $quizData = json_decode($lesson->quiz_data, true);

        if (!$quizData) {
            return response()->json(['success' => false, 'message' => 'No quiz available.'], 422);
        }

        $questions = isset($quizData[0]['question']) ? $quizData : ($quizData['questions'] ?? []);
        $answers   = $request->answers ?? [];

        $correct  = 0; $wrongIds = [];
        foreach ($questions as $i => $q) {
            $userAns   = $answers['q' . ($i + 1)] ?? null;
            $rightAns  = $q['correct_answer'] ?? $q['correct'] ?? null;
            if ($userAns && $userAns === $rightAns) { $correct++; }
            else { $wrongIds[] = $i + 1; }
        }

        $total  = count($questions);
        $score  = $total > 0 ? round(($correct / $total) * 100) : 0;
        $passed = $score >= 70;

        $subjectId = DB::table('external_topics')->where('id', $lesson->topic_id)->value('subject_id');

        DB::table('quiz_performance')->insert([
            'student_id'         => $student->id,
            'lesson_id'          => $lessonId,
            'topic_id'           => $lesson->topic_id,
            'subject_id'         => $subjectId,
            'score'              => $score,
            'total_questions'    => $total,
            'correct_answers'    => $correct,
            'wrong_answers'      => $total - $correct,
            'wrong_question_ids' => json_encode($wrongIds),
            'passed'             => $passed,
            'completed_at'       => now(),
            'attempt_number'     => DB::table('quiz_performance')->where('student_id', $student->id)->where('lesson_id', $lessonId)->count() + 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        UserExternalLessonProgress::updateOrCreate(
            ['user_id' => $student->id, 'lesson_id' => $lessonId],
            ['status' => $passed ? 'completed' : 'in_progress', 'quiz_score' => $score, 'completed_at' => $passed ? now() : null]
        );

        return response()->json([
            'success' => true, 'score' => $score,
            'correct_answers' => $correct, 'total_questions' => $total,
            'passed' => $passed,
            'message' => $passed ? '🎉 Great job!' : '📚 Keep practicing!',
        ]);
    }
}
<?php

namespace App\Http\Controllers\API;

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
    private function oakApiUrl(): string
    {
        return rtrim(config('services.oak.api_url'), '/');
    }

    private function oakApiKey(): string
    {
        return config('services.oak.api_key');
    }

    // =========================================================
    // GET ALL LESSONS FOR A TOPIC
    // =========================================================

    public function indexByTopic($topicId)
    {
        $topic = ExternalTopic::with('lessons')->findOrFail($topicId);

        return response()->json([
            'success' => true,
            'topic'   => $topic,
        ]);
    }

    // =========================================================
    // GET SINGLE LESSON — with lazy Oak content fetch
    // =========================================================

    public function show($id)
    {
        $lesson = ExternalLesson::with('topic.subject')->findOrFail($id);
        $user   = auth()->user();

        // ── Lazy-load Oak content if lesson has no video/quiz yet ──
        if ($this->needsOakContent($lesson)) {
            $lesson = $this->fetchAndStoreOakContent($lesson);
        }

        $progress = UserExternalLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $id)
            ->first();

        return response()->json([
            'success'  => true,
            'lesson'   => $lesson,
            'progress' => $progress,
        ]);
    }

    /**
     * Does this lesson need content fetched from Oak?
     */
    private function needsOakContent(ExternalLesson $lesson): bool
    {
        // Only Oak lessons (has external_id, no video yet)
        return !empty($lesson->external_id)
            && empty($lesson->video_url)
            && empty($lesson->quiz_data)
            && $lesson->topic?->subject?->source === 'Oak National Academy';
    }

    /**
     * Fetch lesson content from Oak API and store it.
     * Uses /lessons/{slug}/summary and /lessons/{slug}/quiz
     */
    private function fetchAndStoreOakContent(ExternalLesson $lesson): ExternalLesson
    {
        $slug = $lesson->external_id;
        Log::info("Oak: Fetching content for lesson slug: {$slug}");

        $updates = [];

        try {
            // ── 1. Fetch lesson summary (includes Mux video ID) ──
            $summaryResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->oakApiKey(),
                'Accept'        => 'application/json',
            ])->timeout(15)->get($this->oakApiUrl() . "/lessons/{$slug}/summary");

            if ($summaryResponse->successful()) {
                $summary = $summaryResponse->json();

                // Build Mux video URL from playback ID
                $muxId = $summary['videoMuxPlaybackId'] ?? null;
                if ($muxId) {
                    $updates['video_url'] = "https://stream.mux.com/{$muxId}.m3u8";
                }

                // Store key learning points as description
                if (!empty($summary['keyLearningPoints'])) {
                    $points = array_map(fn($p) => $p['keyLearningPoint'] ?? '', $summary['keyLearningPoints']);
                    $updates['description'] = implode(' | ', array_filter($points));
                }

                // Store slide deck URL if available
                if (!empty($summary['slideUrl'])) {
                    $updates['slide_url'] = $summary['slideUrl'];
                }
            }

            // ── 2. Fetch quiz data ────────────────────────────────
            $quizResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->oakApiKey(),
                'Accept'        => 'application/json',
            ])->timeout(15)->get($this->oakApiUrl() . "/lessons/{$slug}/quiz");

            if ($quizResponse->successful()) {
                $quizRaw = $quizResponse->json();

                // Normalise Oak quiz format → our simple format
                $normalised = $this->normaliseOakQuiz($quizRaw);

                if (!empty($normalised)) {
                    $updates['quiz_data'] = json_encode($normalised);
                }
            }

        } catch (\Exception $e) {
            Log::error("Oak: Failed to fetch content for {$slug}: " . $e->getMessage());
        }

        // ── Persist updates to DB ─────────────────────────────────
        if (!empty($updates)) {
            DB::table('external_lessons')
                ->where('id', $lesson->id)
                ->update(array_merge($updates, ['updated_at' => now()]));

            // Refresh model
            $lesson = ExternalLesson::with('topic.subject')->find($lesson->id);
            Log::info("Oak: Stored content for lesson {$lesson->id}", array_keys($updates));
        }

        return $lesson;
    }

    /**
     * Convert Oak quiz format to our simple format.
     * Oak: { starterQuiz: [...], exitQuiz: [...] }
     * Ours: [{ question, options, correct_index, correct }]
     */
    private function normaliseOakQuiz(array $raw): array
    {
        $questions = [];

        // Use exitQuiz preferably, fall back to starterQuiz
        $sourceQuestions = !empty($raw['exitQuiz'])
            ? $raw['exitQuiz']
            : ($raw['starterQuiz'] ?? []);

        foreach ($sourceQuestions as $q) {
            // Extract question text
            $questionText = $q['questionStem']['text']
                ?? $q['question']
                ?? null;

            if (!$questionText) continue;

            // Extract answer options
            $options = [];
            foreach ($q['answers'] ?? [] as $answer) {
                $text = $answer['answer']['text'] ?? $answer['text'] ?? null;
                if ($text) $options[] = $text;
            }

            if (count($options) < 2) continue;

            // Find correct answer index
            $correctText  = $q['correctAnswer']['answer']['text']
                ?? $q['correct_answer']
                ?? null;

            $correctIndex = 0;
            if ($correctText) {
                $found = array_search($correctText, $options);
                if ($found !== false) {
                    $correctIndex = $found;
                }
            }

            $questions[] = [
                'question'      => $questionText,
                'options'       => $options,
                'correct_index' => $correctIndex,
                'correct'       => $options[$correctIndex] ?? null,
            ];
        }

        return $questions;
    }

    // =========================================================
    // UPDATE LESSON PROGRESS
    // =========================================================

    public function updateProgress(Request $request, $id)
    {
        $user = auth()->user();

        $progress = UserExternalLessonProgress::updateOrCreate(
            ['user_id'   => $user->id, 'lesson_id' => $id],
            [
                'status'        => $request->status ?? 'in_progress',
                'video_watched' => $request->video_watched ?? false,
                'started_at'    => $request->status === 'in_progress' ? now() : null,
            ]
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Progress updated',
            'progress' => $progress,
        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'No quiz data available for this lesson.',
            ], 422);
        }

        // ── Detect quiz format and score it ──────────────────────
        $score = 0;

        // Format A: Nigerian seeder format — top-level array of questions
        // [{ question, options, correct_index, correct }]
        if (isset($quizData[0]['question'])) {
            [$score, $correct, $total, $wrongIds] = $this->scoreSimpleQuiz($quizData, $request->answers ?? []);
        }
        // Format B: normalised Oak format (same structure after normalisation)
        elseif (isset($quizData[0]['question'])) {
            [$score, $correct, $total, $wrongIds] = $this->scoreSimpleQuiz($quizData, $request->answers ?? []);
        }
        // Format C: legacy format { questions: [...] }
        elseif (isset($quizData['questions'])) {
            [$score, $correct, $total, $wrongIds] = $this->scoreLegacyQuiz($quizData, $request->answers ?? []);
        } else {
            return response()->json(['success' => false, 'message' => 'Unknown quiz format.'], 422);
        }

        $passThreshold = 70;
        $passed = $score >= $passThreshold;

        // ── Save quiz performance ─────────────────────────────────
        $subjectId = DB::table('external_topics')
            ->where('id', $lesson->topic_id)
            ->value('subject_id');

        DB::table('quiz_performance')->insert([
            'student_id'        => $student->id,
            'lesson_id'         => $lessonId,
            'topic_id'          => $lesson->topic_id,
            'subject_id'        => $subjectId,
            'score'             => $score,
            'total_questions'   => $total,
            'correct_answers'   => $correct,
            'wrong_answers'     => $total - $correct,
            'wrong_question_ids'=> json_encode($wrongIds),
            'passed'            => $passed,
            'completed_at'      => now(),
            'attempt_number'    => DB::table('quiz_performance')
                ->where('student_id', $student->id)
                ->where('lesson_id', $lessonId)
                ->count() + 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // ── Update lesson progress ────────────────────────────────
        UserExternalLessonProgress::updateOrCreate(
            ['user_id' => $student->id, 'lesson_id' => $lessonId],
            [
                'status'        => $passed ? 'completed' : 'in_progress',
                'quiz_score'    => $score,
                'video_watched' => true,
                'completed_at'  => $passed ? now() : null,
            ]
        );

        return response()->json([
            'success'         => true,
            'score'           => $score,
            'correct_answers' => $correct,
            'total_questions' => $total,
            'passed'          => $passed,
            'message'         => $passed ? '🎉 Great job! Well done!' : '📚 Keep practicing — you can do it!',
        ]);
    }

    // =========================================================
    // QUIZ SCORING HELPERS
    // =========================================================

    /**
     * Score our normalised format: [{ question, options, correct_index, correct }]
     * Answers come in as: { "0": "optionText", "1": "optionText" }
     */
    private function scoreSimpleQuiz(array $questions, array $answers): array
    {
        $correct  = 0;
        $wrongIds = [];

        foreach ($questions as $i => $q) {
            $userAnswer    = $answers[(string)$i] ?? $answers[$i] ?? null;
            $correctAnswer = $q['correct'] ?? ($q['options'][$q['correct_index']] ?? null);

            if ($userAnswer !== null && $userAnswer === $correctAnswer) {
                $correct++;
            } else {
                $wrongIds[] = $i + 1;
            }
        }

        $total = count($questions);
        $score = $total > 0 ? round(($correct / $total) * 100) : 0;

        return [$score, $correct, $total, $wrongIds];
    }

    /**
     * Score legacy format: { questions: [{ correct_answer: "..." }] }
     * Answers come in as: { "q1": "answer", "q2": "answer" }
     */
    private function scoreLegacyQuiz(array $quizData, array $answers): array
    {
        $questions = $quizData['questions'] ?? [];
        $correct   = 0;
        $wrongIds  = [];

        foreach ($questions as $i => $question) {
            $key        = 'q' . ($i + 1);
            $userAnswer = $answers[$key] ?? null;

            if ($userAnswer === $question['correct_answer']) {
                $correct++;
            } else {
                $wrongIds[] = $i + 1;
            }
        }

        $total = count($questions);
        $score = $total > 0 ? round(($correct / $total) * 100) : 0;

        return [$score, $correct, $total, $wrongIds];
    }
}

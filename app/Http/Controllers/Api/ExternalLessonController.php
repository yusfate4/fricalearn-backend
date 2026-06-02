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
        return response()->json(['success' => true, 'topic' => $topic]);
    }

    // =========================================================
    // GET SINGLE LESSON — lazy fetch Oak content on first open
    // =========================================================

    public function show($id)
    {
        $lesson = ExternalLesson::with('topic.subject')->findOrFail($id);
        $user   = auth()->user();

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

    private function needsOakContent(ExternalLesson $lesson): bool
    {
        // Needs fetch if: has external_id, never been fetched (no description), is Oak content
        return !empty($lesson->external_id)
            && empty($lesson->description)
            && $lesson->topic?->subject?->source === 'Oak National Academy';
    }

    private function fetchAndStoreOakContent(ExternalLesson $lesson): ExternalLesson
    {
        $slug = $lesson->external_id;
        Log::info("Oak: Fetching content for: {$slug}");

        $updates = [];

        try {
            // ── 1. Summary: lesson outcome, key points, keywords, Oak URL ──
            $summaryRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->oakApiKey(),
                'Accept'        => 'application/json',
            ])->timeout(20)->get($this->oakApiUrl() . "/lessons/{$slug}/summary");

            if ($summaryRes->successful()) {
                $summary = $summaryRes->json();

                // Oak page URL for "Watch on Oak" link — stored in slide_url
                if (!empty($summary['canonicalUrl'])) {
                    $updates['slide_url'] = $summary['canonicalUrl'];
                }

                // Build description: pupil outcome + key learning points
                $descParts = [];

                if (!empty($summary['pupilLessonOutcome'])) {
                    $descParts[] = $summary['pupilLessonOutcome'];
                }

                foreach ($summary['keyLearningPoints'] ?? [] as $p) {
                    if (!empty($p['keyLearningPoint'])) {
                        $descParts[] = '• ' . $p['keyLearningPoint'];
                    }
                }

                // Store keywords as JSON in worksheet_url field (reusing existing column)
                if (!empty($summary['lessonKeywords'])) {
                    $updates['worksheet_url'] = json_encode($summary['lessonKeywords']);
                }

                $updates['description'] = !empty($descParts)
                    ? implode("\n", $descParts)
                    : 'fetched'; // Mark as fetched even if no content
            }

            // ── 2. Quiz: only text-based questions (skip image-based) ──
            $quizRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->oakApiKey(),
                'Accept'        => 'application/json',
            ])->timeout(20)->get($this->oakApiUrl() . "/lessons/{$slug}/quiz");

            if ($quizRes->successful()) {
                $normalised = $this->normaliseOakQuiz($quizRes->json());
                if (!empty($normalised)) {
                    $updates['quiz_data'] = json_encode($normalised);
                    Log::info("Oak: Got " . count($normalised) . " text-based quiz questions for {$slug}");
                } else {
                    Log::info("Oak: No text-only quiz questions for {$slug} (all image-based)");
                }
            }

        } catch (\Exception $e) {
            Log::error("Oak: Failed to fetch content for {$slug}: " . $e->getMessage());
            $updates['description'] = 'fetched'; // prevent infinite retry
        }

        if (!empty($updates)) {
            DB::table('external_lessons')
                ->where('id', $lesson->id)
                ->update(array_merge($updates, ['updated_at' => now()]));

            $lesson = ExternalLesson::with('topic.subject')->find($lesson->id);
        }

        return $lesson;
    }

    /**
     * Normalise Oak quiz format to our flat array format.
     *
     * Oak format:
     * {
     *   "question": "Is this a clock?",
     *   "questionType": "multiple-choice",
     *   "questionImage": { "url": "...", ... },   ← SKIP if present
     *   "answers": [
     *     { "type": "text", "content": "Yes", "distractor": true },   ← wrong
     *     { "type": "text", "content": "No",  "distractor": false },  ← correct
     *   ]
     * }
     *
     * Our format:
     * [{ question, options, correct_answer, correct_index }]
     */
    private function normaliseOakQuiz(array $raw): array
    {
        $questions = [];

        // Try exitQuiz first, fall back to starterQuiz
        $source = !empty($raw['exitQuiz'])
            ? $raw['exitQuiz']
            : ($raw['starterQuiz'] ?? []);

        foreach ($source as $q) {
            $questionText = $q['question'] ?? null;
            if (!$questionText) continue;

            // ── Skip image-based questions ──────────────────────────
            // These require seeing an image to answer (e.g. "Which is the hour hand?")
            if (!empty($q['questionImage'])) continue;

            // ── Extract answers ─────────────────────────────────────
            $options       = [];
            $correctAnswer = null;

            foreach ($q['answers'] ?? [] as $answer) {
                $content     = $answer['content'] ?? null;
                $isDistractor = $answer['distractor'] ?? true; // true = wrong, false = correct

                if (empty($content)) continue;

                // Skip single-letter answers like "a", "b", "c"
                // These reference labelled parts of an image — meaningless without the image
                if (strlen(trim($content)) === 1 && ctype_alpha($content)) continue;

                $options[] = $content;

                if ($isDistractor === false) {
                    $correctAnswer = $content;
                }
            }

            if (count($options) < 2 || !$correctAnswer) continue;

            // Ensure correct answer is in the options array
            if (!in_array($correctAnswer, $options)) continue;

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
            return response()->json([
                'success' => false,
                'message' => 'No quiz available for this lesson.',
            ], 422);
        }

        $answers = $request->answers ?? [];

        // Handle both flat array and legacy {questions:[]} wrapper
        $questions = isset($quizData[0]['question'])
            ? $quizData
            : ($quizData['questions'] ?? []);

        [$score, $correct, $total, $wrongIds] = $this->scoreQuiz($questions, $answers);

        $passed = $score >= 70;

        $subjectId = DB::table('external_topics')
            ->where('id', $lesson->topic_id)
            ->value('subject_id');

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
            'attempt_number'     => DB::table('quiz_performance')
                ->where('student_id', $student->id)
                ->where('lesson_id', $lessonId)
                ->count() + 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

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
            'message'         => $passed
                ? '🎉 Great job! Well done!'
                : '📚 Keep practicing — you can do it!',
        ]);
    }

    /**
     * Score quiz. Viewer sends { "q1": "answer text", "q2": "..." }
     * Correct answer is in correct_answer OR correct field.
     */
    private function scoreQuiz(array $questions, array $answers): array
    {
        $correct  = 0;
        $wrongIds = [];

        foreach ($questions as $i => $q) {
            $userAnswer    = $answers['q' . ($i + 1)] ?? null;
            $correctAnswer = $q['correct_answer'] ?? $q['correct'] ?? null;

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
}

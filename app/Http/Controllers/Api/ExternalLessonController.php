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
    // GET SINGLE LESSON — lazy-fetch Oak content on first open
    // =========================================================

    public function show($id)
    {
        $lesson = ExternalLesson::with('topic.subject')->findOrFail($id);
        $user   = auth()->user();

        // Lazy-load Oak content if video/quiz not yet fetched
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
        return !empty($lesson->external_id)
            && empty($lesson->video_url)
            && empty($lesson->quiz_data)
            && $lesson->topic?->subject?->source === 'Oak National Academy';
    }

    private function fetchAndStoreOakContent(ExternalLesson $lesson): ExternalLesson
    {
        $slug = $lesson->external_id;
        Log::info("Oak: Fetching content for: {$slug}");

        $updates = [];

        try {
            // ── 1. Lesson summary (Mux video ID + key learning points) ──
            $summaryRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->oakApiKey(),
                'Accept'        => 'application/json',
            ])->timeout(20)->get($this->oakApiUrl() . "/lessons/{$slug}/summary");

            if ($summaryRes->successful()) {
                $summary = $summaryRes->json();

                $muxId = $summary['videoMuxPlaybackId'] ?? null;
                if ($muxId) {
                    // ✅ Use Mux hosted player URL — works as a standard iframe src
                    $updates['video_url'] = "https://player.mux.com/{$muxId}";
                }

                if (!empty($summary['keyLearningPoints'])) {
                    $points = array_map(
                        fn($p) => $p['keyLearningPoint'] ?? '',
                        $summary['keyLearningPoints']
                    );
                    $updates['description'] = implode(' • ', array_filter($points));
                }
            }

            // ── 2. Quiz data ────────────────────────────────────────
            $quizRes = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->oakApiKey(),
                'Accept'        => 'application/json',
            ])->timeout(20)->get($this->oakApiUrl() . "/lessons/{$slug}/quiz");

            if ($quizRes->successful()) {
                $normalised = $this->normaliseOakQuiz($quizRes->json());
                if (!empty($normalised)) {
                    $updates['quiz_data'] = json_encode($normalised);
                }
            }

        } catch (\Exception $e) {
            Log::error("Oak: Failed to fetch content for {$slug}: " . $e->getMessage());
        }

        if (!empty($updates)) {
            DB::table('external_lessons')
                ->where('id', $lesson->id)
                ->update(array_merge($updates, ['updated_at' => now()]));

            $lesson = ExternalLesson::with('topic.subject')->find($lesson->id);
            Log::info("Oak: Content stored for lesson {$lesson->id}");
        }

        return $lesson;
    }

    /**
     * Normalise Oak quiz format → our format matching the viewer.
     *
     * Oak: { starterQuiz: [{questionStem, answers, correctAnswer}], exitQuiz: [...] }
     * Ours: [{ question, options, correct_answer, correct_index }]
     *                       ^^^^^^^^^^^^^^
     *                       matches viewer's question.correct_answer
     */
    private function normaliseOakQuiz(array $raw): array
    {
        $questions = [];

        // Prefer exitQuiz, fall back to starterQuiz
        $source = !empty($raw['exitQuiz'])
            ? $raw['exitQuiz']
            : ($raw['starterQuiz'] ?? []);

        foreach ($source as $q) {
            $questionText = $q['questionStem']['text'] ?? $q['question'] ?? null;
            if (!$questionText) continue;

            // Extract answer options as plain strings
            $options = [];
            foreach ($q['answers'] ?? [] as $answer) {
                $text = $answer['answer']['text'] ?? $answer['text'] ?? null;
                if ($text) $options[] = $text;
            }
            if (count($options) < 2) continue;

            // Find correct answer
            $correctText = $q['correctAnswer']['answer']['text']
                ?? $q['correct_answer']
                ?? null;

            $correctIndex = 0;
            if ($correctText) {
                $idx = array_search($correctText, $options);
                if ($idx !== false) $correctIndex = $idx;
            }

            $questions[] = [
                'question'      => $questionText,
                'options'       => $options,
                // ✅ Use 'correct_answer' to match viewer's question.correct_answer
                'correct_answer' => $options[$correctIndex] ?? null,
                'correct_index'  => $correctIndex,
                'explanation'    => null, // Oak doesn't provide explanations
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
                'message' => 'No quiz available for this lesson yet.',
            ], 422);
        }

        $answers = $request->answers ?? [];

        // Detect format and score
        // Our format (both Oak normalised + Nigerian): flat array [{ question, options, correct_answer }]
        if (isset($quizData[0]['question'])) {
            [$score, $correct, $total, $wrongIds] = $this->scoreQuiz($quizData, $answers);
        }
        // Legacy format: { questions: [...] }
        elseif (isset($quizData['questions'])) {
            [$score, $correct, $total, $wrongIds] = $this->scoreQuiz($quizData['questions'], $answers);
        } else {
            return response()->json(['success' => false, 'message' => 'Unknown quiz format.'], 422);
        }

        $passed = $score >= 70;

        // Save performance
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

        // Update lesson progress
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
     * Score quiz. Answers from viewer come as { "q1": "option text", "q2": "..." }
     * Matches against correct_answer OR correct field.
     */
    private function scoreQuiz(array $questions, array $answers): array
    {
        $correct  = 0;
        $wrongIds = [];

        foreach ($questions as $i => $q) {
            // Viewer sends q1, q2, q3...
            $userAnswer    = $answers['q' . ($i + 1)] ?? null;
            // Support both field names: correct_answer (Oak/new) and correct (old Nigerian)
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

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
    private function oakApiUrl(): string { return rtrim(config('services.oak.api_url'), '/'); }
    private function oakApiKey(): string { return config('services.oak.api_key'); }

    // =========================================================
    // GET ALL LESSONS FOR A TOPIC
    // =========================================================

    public function indexByTopic($topicId)
    {
        $topic = ExternalTopic::with('lessons')->findOrFail($topicId);
        return response()->json(['success' => true, 'topic' => $topic]);
    }

    // =========================================================
    // GET SINGLE LESSON — lazy fetch if not yet populated
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
        return !empty($lesson->external_id)
            && empty($lesson->description)
            && $lesson->topic?->subject?->source === 'Oak National Academy';
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
                $metadata['key_points']     = array_values(array_filter(array_map(fn($p) => $p['keyLearningPoint'] ?? null, $s['keyLearningPoints'] ?? [])));
                $metadata['keywords']       = array_map(fn($kw) => ['keyword' => $kw['keyword'] ?? '', 'description' => $kw['description'] ?? ''], $s['lessonKeywords'] ?? []);
                $metadata['misconceptions'] = array_map(fn($m) => ['misconception' => $m['misconception'] ?? '', 'response' => $m['response'] ?? ''], $s['misconceptionsAndCommonMistakes'] ?? []);
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

            // No video or external links
            $updates['video_url'] = null;
            $updates['slide_url'] = null;

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

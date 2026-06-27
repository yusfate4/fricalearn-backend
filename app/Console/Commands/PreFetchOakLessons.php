<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PreFetchOakLessons extends Command
{
    protected $signature   = 'oak:prefetch {--limit=50} {--subject=39}';
    protected $description = 'Fetch Oak lesson content (transcript + metadata + quiz) — no video links';

    public function handle(): int
    {
        $apiUrl    = rtrim(config('services.oak.api_url'), '/');
        $apiKey    = config('services.oak.api_key');
        $limit     = (int) $this->option('limit');
        $subjectId = (int) $this->option('subject');

        $lessons = DB::table('external_lessons as l')
            ->join('external_topics as t', 't.id', '=', 'l.topic_id')
            ->join('external_subjects as s', 's.id', '=', 't.subject_id')
            ->where('s.source', 'Oak National Academy')
            ->where('t.subject_id', $subjectId)
            ->whereNotNull('l.external_id')
            ->whereNull('l.description')
            ->select('l.id', 'l.external_id', 'l.title')
            ->limit($limit)
            ->get();

        $this->info("Found {$lessons->count()} lessons (subject #{$subjectId}, limit: {$limit})");
        $this->newLine();

        $fetched       = 0;
        $hasTranscript = 0;
        $hasQuiz       = 0;

        foreach ($lessons as $lesson) {
            $slug    = $lesson->external_id;
            $updates = [];

            $this->line("Fetching: {$lesson->title}");

            try {
                // ── 1. SUMMARY: outcome, key points, keywords, misconceptions ──
                $summaryRes = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept'        => 'application/json',
                ])->timeout(20)->get("{$apiUrl}/lessons/{$slug}/summary");

                $metadata = [
                    'outcome'        => null,
                    'key_points'     => [],
                    'keywords'       => [],
                    'misconceptions' => [],
                ];

                if ($summaryRes->successful()) {
                    $summary = $summaryRes->json();

                    $metadata['outcome'] = $summary['pupilLessonOutcome'] ?? null;

                    $metadata['key_points'] = array_values(array_filter(
                        array_map(
                            fn($p) => $p['keyLearningPoint'] ?? null,
                            $summary['keyLearningPoints'] ?? []
                        )
                    ));

                    $metadata['keywords'] = array_map(
                        fn($kw) => [
                            'keyword'     => $kw['keyword']     ?? '',
                            'description' => $kw['description'] ?? '',
                        ],
                        $summary['lessonKeywords'] ?? []
                    );

                    $metadata['misconceptions'] = array_map(
                        fn($m) => [
                            'misconception' => $m['misconception'] ?? '',
                            'response'      => $m['response']      ?? '',
                        ],
                        $summary['misconceptionsAndCommonMistakes'] ?? []
                    );
                }

                // Store all metadata as JSON in worksheet_url (TEXT column)
                $updates['worksheet_url'] = json_encode($metadata);

                // ── 2. TRANSCRIPT: the full lesson text ────────────────────────
                $transcriptRes = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept'        => 'application/json',
                ])->timeout(20)->get("{$apiUrl}/lessons/{$slug}/transcript");

                if ($transcriptRes->successful()) {
                    $transcriptData = $transcriptRes->json();
                    $transcript     = $transcriptData['transcript'] ?? null;

                    if ($transcript && strlen(trim($transcript)) > 30) {
                        // Store up to 10,000 chars of transcript
                        $updates['description'] = substr(trim($transcript), 0, 10000);
                        $hasTranscript++;
                        $this->line("  📄 Transcript: " . strlen($updates['description']) . " chars");
                    } else {
                        // No transcript — use outcome as fallback marker
                        $updates['description'] = $metadata['outcome'] ?? 'fetched';
                    }
                } else {
                    $updates['description'] = $metadata['outcome'] ?? 'fetched';
                }

                // ── 3. QUIZ: text-only questions ───────────────────────────────
                $quizRes = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept'        => 'application/json',
                ])->timeout(20)->get("{$apiUrl}/lessons/{$slug}/quiz");

                if ($quizRes->successful()) {
                    $raw    = $quizRes->json();
                    $source = !empty($raw['exitQuiz'])
                        ? $raw['exitQuiz']
                        : ($raw['starterQuiz'] ?? []);

                    $questions = [];
                    foreach ($source as $q) {
                        $questionText = $q['question'] ?? null;
                        if (!$questionText) continue;

                        // Skip image-based questions
                        if (!empty($q['questionImage'])) continue;

                        $options       = [];
                        $correctAnswer = null;

                        foreach ($q['answers'] ?? [] as $answer) {
                            $content     = $answer['content'] ?? null;
                            $isDistractor = $answer['distractor'] ?? true;

                            if (empty($content) || !is_string($content)) continue;
                            // Skip single-letter image labels
                            if (strlen(trim($content)) === 1 && ctype_alpha($content)) continue;

                            $options[] = $content;
                            if ($isDistractor === false) $correctAnswer = $content;
                        }

                        if (count($options) < 2 || !$correctAnswer) continue;
                        if (!in_array($correctAnswer, $options)) continue;

                        $questions[] = [
                            'question'       => $questionText,
                            'options'        => $options,
                            'correct_answer' => $correctAnswer,
                            'correct_index'  => array_search($correctAnswer, $options),
                            'explanation'    => null,
                        ];
                    }

                    if (!empty($questions)) {
                        $updates['quiz_data'] = json_encode($questions);
                        $hasQuiz++;
                        $this->line("  📝 Quiz: " . count($questions) . " questions");
                    }
                }

                // Ensure no Oak video/page links stored
                $updates['video_url'] = null;
                $updates['slide_url'] = null;

            } catch (\Exception $e) {
                $this->warn("  ❌ Error: " . $e->getMessage());
                $updates['description'] = 'fetched';
            }

            DB::table('external_lessons')
                ->where('id', $lesson->id)
                ->update(array_merge($updates, ['updated_at' => now()]));

            $fetched++;
            usleep(350000); // 0.35s between requests
        }

        $this->newLine();
        $this->info("Done! Fetched: {$fetched} | Has transcript: {$hasTranscript} | Has quiz: {$hasQuiz}");
        return self::SUCCESS;
    }
}

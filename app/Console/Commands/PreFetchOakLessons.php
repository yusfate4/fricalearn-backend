<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PreFetchOakLessons extends Command
{
    protected $signature   = 'oak:prefetch {--limit=50} {--subject=39}';
    protected $description = 'Pre-fetch Oak content (summary + transcript + quiz) for all lessons';

    public function handle(): int
    {
        $apiUrl   = rtrim(config('services.oak.api_url'), '/');
        $apiKey   = config('services.oak.api_key');
        $limit    = (int) $this->option('limit');
        $subjectId = (int) $this->option('subject');

        $lessons = DB::table('external_lessons as l')
            ->join('external_topics as t', 't.id', '=', 'l.topic_id')
            ->join('external_subjects as s', 's.id', '=', 't.subject_id')
            ->where('s.source', 'Oak National Academy')
            ->where('t.subject_id', $subjectId)
            ->whereNotNull('l.external_id')
            ->whereNull('l.description')   // only not-yet-fetched
            ->select('l.id', 'l.external_id', 'l.title')
            ->limit($limit)
            ->get();

        $this->info("Found {$lessons->count()} lessons to fetch (subject #{$subjectId}, limit: {$limit})");
        $this->newLine();

        $fetched   = 0;
        $hasQuiz   = 0;
        $hasOakUrl = 0;

        foreach ($lessons as $lesson) {
            $slug    = $lesson->external_id;
            $updates = [];

            $this->line("Fetching: {$lesson->title}");

            try {
                // ── 1. SUMMARY ─────────────────────────────────────
                $summaryRes = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept'        => 'application/json',
                ])->timeout(15)->get("{$apiUrl}/lessons/{$slug}/summary");

                if ($summaryRes->successful()) {
                    $summary = $summaryRes->json();

                    // Oak page link (stored in slide_url)
                    if (!empty($summary['canonicalUrl'])) {
                        $updates['slide_url'] = $summary['canonicalUrl'];
                        $hasOakUrl++;
                    }

                    // Keywords (stored as JSON in worksheet_url)
                    if (!empty($summary['lessonKeywords'])) {
                        $updates['worksheet_url'] = json_encode($summary['lessonKeywords']);
                    }

                    // Description = pupilLessonOutcome + keyLearningPoints
                    $descParts = [];
                    if (!empty($summary['pupilLessonOutcome'])) {
                        $descParts[] = $summary['pupilLessonOutcome'];
                    }
                    foreach ($summary['keyLearningPoints'] ?? [] as $p) {
                        $kp = $p['keyLearningPoint'] ?? '';
                        if ($kp) $descParts[] = '• ' . $kp;
                    }
                    $updates['description'] = !empty($descParts)
                        ? implode("\n", $descParts)
                        : 'fetched';
                } else {
                    $updates['description'] = 'fetched'; // mark as attempted
                }

                // ── 2. QUIZ ─────────────────────────────────────────
                // Actual Oak quiz format (confirmed from API):
                // { starterQuiz: [{question, questionType, questionImage?, answers:[{type,content,distractor}]}], exitQuiz: [...] }
                // distractor: false = CORRECT answer
                // distractor: true  = wrong answer
                $quizRes = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept'        => 'application/json',
                ])->timeout(15)->get("{$apiUrl}/lessons/{$slug}/quiz");

                if ($quizRes->successful()) {
                    $raw = $quizRes->json();

                    // Prefer exitQuiz, fall back to starterQuiz
                    $source = !empty($raw['exitQuiz'])
                        ? $raw['exitQuiz']
                        : ($raw['starterQuiz'] ?? []);

                    $questions = [];

                    foreach ($source as $q) {
                        $questionText = $q['question'] ?? null;
                        if (!$questionText) continue;

                        // Skip image-based questions (require seeing a picture to answer)
                        if (!empty($q['questionImage'])) continue;

                        $options       = [];
                        $correctAnswer = null;

                        foreach ($q['answers'] ?? [] as $answer) {
                            $content     = $answer['content'] ?? null;
                            $isDistractor = $answer['distractor'] ?? true;

                           if (empty($content)) continue;

// Skip non-string content (some Oak questions use arrays for order/match types)
if (!is_string($content)) continue;

// Skip single-letter options like "a","b","c" = image labels
if (strlen(trim($content)) === 1 && ctype_alpha($content)) continue;

                            $options[] = $content;

                            if ($isDistractor === false) {
                                $correctAnswer = $content;
                            }
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
                        $this->line("  ✅ " . count($questions) . " quiz questions");
                    }
                }

                // ── 3. TRANSCRIPT (as backup readable content) ─────
                // Only fetch if description is still empty after summary
                if (($updates['description'] ?? 'fetched') === 'fetched') {
                    $transcriptRes = Http::withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Accept'        => 'application/json',
                    ])->timeout(15)->get("{$apiUrl}/lessons/{$slug}/transcript");

                    if ($transcriptRes->successful()) {
                        $transcriptData = $transcriptRes->json();
                        $transcript     = $transcriptData['transcript'] ?? null;

                        if ($transcript) {
                            // Store first 600 chars as lesson overview
                            $excerpt = substr(strip_tags($transcript), 0, 600);
                            if (strlen($transcript) > 600) $excerpt .= '...';
                            $updates['description'] = $excerpt;
                            $this->line("  📄 Transcript stored as content");
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->warn("  Error: " . $e->getMessage());
                $updates['description'] = $updates['description'] ?? 'fetched';
            }

            DB::table('external_lessons')
                ->where('id', $lesson->id)
                ->update(array_merge($updates, ['updated_at' => now()]));

            $fetched++;
            usleep(300000); // 0.3s between requests — stay within 1000/hr limit
        }

        $this->newLine();
        $this->info("Done! Fetched: {$fetched} | Has quiz: {$hasQuiz} | Has Oak link: {$hasOakUrl}");
        return self::SUCCESS;
    }
}

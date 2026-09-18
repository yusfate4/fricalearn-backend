<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenAI;

class GenerateLessonQuizzes extends Command
{
    protected $signature   = 'oak:generate-quizzes {--limit=10} {--subject=}';
    protected $description = 'Generate AI quizzes for lessons that have a transcript but no quiz';

    /**
     * Strip all non-UTF-8-safe bytes from a string.
     * Oak lesson titles contain curly quotes (U+2018/2019) that get
     * stored as malformed sequences and cause json_encode to throw.
     */
    private function safeUtf8(string $str): string
    {
        // Convert to UTF-8, ignoring invalid bytes
        $str = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
        // Strip control characters except tab/newline/CR
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
        // Replace common typographic characters Oak uses
        $map = [
            '\xE2\x80\x98' => "'",  // U+2018 LEFT SINGLE QUOTATION MARK
            '\xE2\x80\x99' => "'",  // U+2019 RIGHT SINGLE QUOTATION MARK
            '\xE2\x80\x9C' => '"',  // U+201C LEFT DOUBLE QUOTATION MARK
            '\xE2\x80\x9D' => '"',  // U+201D RIGHT DOUBLE QUOTATION MARK
            '\xE2\x80\x93' => '-',  // U+2013 EN DASH
            '\xE2\x80\x94' => '-',  // U+2014 EM DASH
            '\xE2\x80\xA6' => '...', // U+2026 HORIZONTAL ELLIPSIS
        ];
        foreach ($map as $bytes => $replacement) {
            $str = str_replace($bytes, $replacement, $str);
        }
        // Final safety: ensure the result is valid UTF-8
        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        }
        return $str;
    }

    public function handle(): int
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            $this->error('OPENAI_API_KEY missing in .env');
            return self::FAILURE;
        }

        $client  = OpenAI::client($apiKey);
        $limit   = (int) $this->option('limit');
        $subject = $this->option('subject');

        // Lessons with real transcript content but no quiz
        $query = DB::table('external_lessons as l')
            ->join('external_topics as t', 't.id', '=', 'l.topic_id')
            ->join('external_subjects as s', 's.id', '=', 't.subject_id')
            ->where('s.source', 'Oak National Academy')
            ->whereNull('l.quiz_data')
            ->whereNotNull('l.description')
            ->where('l.description', '!=', 'fetched')
            ->whereRaw('CHAR_LENGTH(l.description) > 300') // real transcript, not just outcome
            ->select('l.id', 'l.title', 'l.description', 's.name as subject_name');

        if ($subject) {
            $query->where('t.subject_id', (int) $subject);
        }

        $lessons = $query->limit($limit)->get();

        $this->info("Found {$lessons->count()} lessons needing AI-generated quizzes");
        $generated = 0;

        foreach ($lessons as $lesson) {
            // Sanitise all string fields immediately — Oak uses curly quotes
            // and other non-ASCII bytes that make json_encode throw
            $cleanTitle   = $this->safeUtf8($lesson->title);
            $cleanContent = $this->safeUtf8(substr($lesson->description ?? '', 0, 3000));
            $cleanSubject = $this->safeUtf8($lesson->subject_name ?? '');

            $this->line("Generating: {$cleanTitle}");

            $prompt = "You are creating a quiz for children based on this lesson.

LESSON TITLE: {$cleanTitle}
SUBJECT: {$cleanSubject}

LESSON CONTENT:
{$cleanContent}

Create exactly 4 multiple-choice questions testing understanding of THIS lesson's content.

RULES:
- Questions must be answerable from the lesson content above
- Age-appropriate language for the key stage
- Each question has exactly 4 options
- Only ONE correct answer per question
- Wrong answers should be plausible but clearly incorrect
- Include a one-sentence explanation for each correct answer
- NO questions requiring images or diagrams

Respond ONLY with a valid JSON array in this exact format, no other text:
[
  {
    \"question\": \"...\",
    \"options\": [\"...\", \"...\", \"...\", \"...\"],
    \"correct_answer\": \"...\",
    \"correct_index\": 0,
    \"explanation\": \"...\"
  }
]

The correct_answer must exactly match one of the options, and correct_index must be its position (0-3).";

            try {
                $response = $client->chat()->create([
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 1200,
                    'temperature' => 0.4,
                ]);

                $raw = trim($response->choices[0]->message->content);
                // Strip markdown fences if the model added them
                $raw = preg_replace('/^```json\s*|\s*```$/', '', $raw);
                // Ensure valid UTF-8 before decoding (some Oak content has encoding issues)
                $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
                $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $raw);

                $questions = json_decode($raw, true);

                // Validate structure
                if (!is_array($questions) || count($questions) < 2) {
                    $this->warn("  ⚠ Invalid response, skipping");
                    continue;
                }

                $valid = [];
                foreach ($questions as $q) {
                    if (empty($q['question']) || empty($q['options']) || empty($q['correct_answer'])) continue;
                    if (count($q['options']) < 2) continue;
                    if (!in_array($q['correct_answer'], $q['options'])) continue;

                    $valid[] = [
                        'question'       => $q['question'],
                        'options'        => array_values($q['options']),
                        'correct_answer' => $q['correct_answer'],
                        'correct_index'  => array_search($q['correct_answer'], array_values($q['options'])),
                        'explanation'    => $q['explanation'] ?? null,
                    ];
                }

                if (count($valid) >= 2) {
                    // Sanitise AI response before saving
                    $validClean = array_map(function ($q) {
                        return [
                            'question'       => $this->safeUtf8($q['question']),
                            'options'        => array_map([$this, 'safeUtf8'], $q['options']),
                            'correct_answer' => $this->safeUtf8($q['correct_answer']),
                            'correct_index'  => $q['correct_index'],
                            'explanation'    => isset($q['explanation']) ? $this->safeUtf8($q['explanation']) : null,
                        ];
                    }, $valid);
                    DB::table('external_lessons')
                        ->where('id', $lesson->id)
                        ->update([
                            'quiz_data'  => json_encode($validClean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                    $generated++;
                    $this->line("  ✅ " . count($valid) . " questions generated");
                } else {
                    $this->warn("  ⚠ Not enough valid questions");
                }

            } catch (\Exception $e) {
                $this->warn("  ❌ Error: " . $e->getMessage());
            }

            usleep(500000); // 0.5s between OpenAI calls
        }

        $this->newLine();
        $this->info("Done! Generated quizzes for {$generated} lessons");
        return self::SUCCESS;
    }
}

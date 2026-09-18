<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenAI;

class GenerateLessonContent extends Command
{
    protected $signature   = 'oak:generate-content {--limit=10} {--subject=}';
    protected $description = 'AI-generate lesson content for lessons where Oak provides no transcript (e.g. copyrighted texts)';

    public function handle(): int
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            $this->error('OPENAI_API_KEY missing in .env');
            return self::FAILURE;
        }

        $client = OpenAI::client($apiKey);
        $limit  = (int) $this->option('limit');

        // Lessons that were fetched but got no usable transcript.
        // (description NULL means not fetched yet — leave those to oak:prefetch-next)
        $query = DB::table('external_lessons as l')
            ->join('external_topics as t', 't.id', '=', 'l.topic_id')
            ->join('external_subjects as s', 's.id', '=', 't.subject_id')
            ->where('s.source', 'Oak National Academy')
            ->whereNotNull('l.description')
            ->where(function ($q) {
                $q->where('l.description', 'fetched')
                  ->orWhereRaw('CHAR_LENGTH(l.description) < 300');
            })
            ->select('l.id', 'l.title', 'l.description', 'l.worksheet_url',
                     't.title as topic_title', 's.name as subject_name', 's.key_stage');

        if ($this->option('subject')) {
            $query->where('t.subject_id', (int) $this->option('subject'));
        }

        $lessons = $query->limit($limit)->get();
        $this->info("Found {$lessons->count()} lessons needing AI-generated content");
        $generated = 0;

        foreach ($lessons as $lesson) {
            $this->line("Generating: {$lesson->title}");

            // Sanitise lesson title — Oak uses curly quotes and special chars
            $lesson->title = iconv('UTF-8', 'UTF-8//IGNORE',
                mb_convert_encoding($lesson->title, 'UTF-8', 'UTF-8')
            );

            // Pull whatever metadata Oak gave us
            $meta = json_decode($lesson->worksheet_url ?? '{}', true) ?: [];
            $outcome  = $meta['outcome'] ?? ($lesson->description !== 'fetched' ? $lesson->description : null);
            $points   = $meta['key_points'] ?? [];
            $keywords = $meta['keywords'] ?? [];

            $pointsText   = $points ? "- " . implode("\n- ", $points) : "(none provided)";
            $keywordsText = $keywords
                ? implode("\n", array_map(function($k) { return "- " . $k['keyword'] . ": " . $k['description']; }, $keywords))
                : "(none provided)";

            $ageGuideMap = [
                'KS1' => 'ages 5-7 (very simple sentences, short paragraphs)',
                'KS2' => 'ages 7-11 (clear, friendly language)',
                'KS3' => 'ages 11-14',
            ];
            $ageGuide = $ageGuideMap[$lesson->key_stage] ?? 'ages 14-16 (GCSE level)';

            $prompt = "Write an educational reading lesson for a child, {$ageGuide}.

SUBJECT: {$lesson->subject_name}
TOPIC: {$lesson->topic_title}
LESSON TITLE: {$lesson->title}
LEARNING OUTCOME: " . ($outcome ?: 'Derive from the title') . "
KEY LEARNING POINTS:
{$pointsText}
KEY VOCABULARY:
{$keywordsText}

RULES:
- Write 6-10 short paragraphs a student can read on their own to learn this topic.
- Teach directly towards the learning outcome and cover every key point.
- Use the key vocabulary naturally and explain each term.
- Warm, encouraging teacher voice. Use examples a child can relate to.
- If the lesson is about a copyrighted book (e.g. a novel), teach the THEMES, CHARACTERS and ANALYSIS SKILLS in your own words — do NOT reproduce passages from the book.
- Plain text only: no markdown symbols, no headings, no bullet characters. Separate paragraphs with a blank line.
- Maximum 700 words.";

            try {
                $response = $client->chat()->create([
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 1100,
                    'temperature' => 0.5,
                ]);

                $content = trim($response->choices[0]->message->content);

                if (strlen($content) > 400) {
                    DB::table('external_lessons')
                        ->where('id', $lesson->id)
                        ->update([
                            'description' => substr($content, 0, 10000),
                            'updated_at'  => now(),
                        ]);
                    $generated++;
                    $this->line("  ✅ " . strlen($content) . " chars generated");
                } else {
                    $this->warn("  ⚠ Response too short, skipping");
                }

            } catch (\Exception $e) {
                $this->warn("  ❌ Error: " . $e->getMessage());
            }

            usleep(500000);
        }

        $this->newLine();
        $this->info("Done! Generated content for {$generated} lessons");
        return self::SUCCESS;
    }
}

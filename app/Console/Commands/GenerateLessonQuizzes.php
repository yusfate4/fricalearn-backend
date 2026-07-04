<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenAI;

class GenerateLessonQuizzes extends Command
{
    protected $signature   = 'oak:generate-quizzes {--limit=10} {--subject=}';
    protected $description = 'Generate AI quizzes for lessons that have a transcript but no quiz';

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
            $this->line("Generating: {$lesson->title}");

            // Use first 3000 chars of transcript (enough context, cheap tokens)
            $content = substr($lesson->description, 0, 3000);

            $prompt = "You are creating a quiz for children based on this lesson.

LESSON TITLE: {$lesson->title}
SUBJECT: {$lesson->subject_name}

LESSON CONTENT:
{$content}

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
                    DB::table('external_lessons')
                        ->where('id', $lesson->id)
                        ->update([
                            'quiz_data'  => json_encode($valid),
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

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncOakCurriculum extends Command
{
    protected $signature = 'oak:sync {key_stage} {subject} {--dry-run}';

    protected $description = 'Sync Oak National Academy curriculum into FricaLearn database';

    public function handle(): int
    {
        $keyStage = strtolower($this->argument('key_stage'));
        $subject  = strtolower($this->argument('subject'));
        $isDryRun = $this->option('dry-run');

        $this->info("Oak Sync | Key Stage: {$keyStage} | Subject: {$subject}");
        $this->info($isDryRun ? 'Mode: DRY RUN' : 'Mode: LIVE');

        $apiUrl = config('services.oak.api_url');
        $apiKey = config('services.oak.api_key');

        // ── Step 1: Fetch units from Oak ────────────────────────
        $this->info('Fetching units from Oak API...');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept'        => 'application/json',
        ])->get("{$apiUrl}/key-stages/{$keyStage}/subject/{$subject}/units");

        if (!$response->successful()) {
            $this->error('Oak API failed: ' . $response->body());
            return self::FAILURE;
        }

        $unitsData  = $response->json();
        $totalUnits = 0;
        foreach ($unitsData as $yearGroup) {
            $totalUnits += count($yearGroup['units'] ?? []);
        }
        $this->info("Found {$totalUnits} units");

        // ── Step 2: Save subject record ──────────────────────────
        $subjectTitle = ucwords(str_replace('-', ' ', $subject)) . ' (' . strtoupper($keyStage) . ')';

        if (!$isDryRun) {
            DB::table('external_subjects')->updateOrInsert(
                [
                    'name'              => $subjectTitle,
                    'curriculum_region' => 'uk',
                ],
                [
                    'key_stage'         => strtoupper($keyStage),
                    'year_group'        => $this->ksToYear($keyStage),
                    'source'            => 'Oak National Academy',
                    'curriculum_region' => 'uk',
                    'framework_code'    => strtoupper($keyStage),
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ]
            );

            $externalSubject = DB::table('external_subjects')
                ->where('name', $subjectTitle)
                ->where('curriculum_region', 'uk')
                ->first();

            $this->info("Subject saved: {$subjectTitle} (ID: {$externalSubject->id})");
        } else {
            $this->info("[DRY RUN] Would save subject: {$subjectTitle}");
        }

        // ── Step 3: Sync units and lessons ───────────────────────
        $topicCount  = 0;
        $lessonCount = 0;

        foreach ($unitsData as $yearGroup) {
            $yearTitle = $yearGroup['yearTitle'] ?? 'Unknown Year';

            $this->line("  {$yearTitle}");

            foreach ($yearGroup['units'] ?? [] as $unit) {
                $unitSlug  = $unit['unitSlug'];
                $unitTitle = $unit['unitTitle'];

                $this->line("    Unit: {$unitTitle}");
                $topicCount++;

                if (!$isDryRun) {
                    // Use title+subject_id as unique key (no slug column in table)
                    DB::table('external_topics')->updateOrInsert(
                        [
                            'title'               => $unitTitle,
                            'external_subject_id' => $externalSubject->id,
                        ],
                        [
                            'title'               => $unitTitle,
                            'external_subject_id' => $externalSubject->id,
                            'order'               => $topicCount,
                            'updated_at'          => now(),
                            'created_at'          => now(),
                        ]
                    );

                    $topic = DB::table('external_topics')
                        ->where('title', $unitTitle)
                        ->where('external_subject_id', $externalSubject->id)
                        ->first();
                }

                // ── Fetch lessons for this unit ──────────────────
                $lessonsResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept'        => 'application/json',
                ])->get("{$apiUrl}/key-stages/{$keyStage}/subject/{$subject}/lessons", [
                    'unit'  => $unitSlug,
                    'limit' => 100,
                ]);

                if (!$lessonsResponse->successful()) {
                    $this->warn("    Could not fetch lessons for: {$unitSlug}");
                    continue;
                }

                $lessonOrder = 1;
                foreach ($lessonsResponse->json() as $unitGroup) {
                    if (($unitGroup['unitSlug'] ?? '') !== $unitSlug) {
                        continue;
                    }

                    foreach ($unitGroup['lessons'] ?? [] as $lesson) {
                        $lessonSlug  = $lesson['lessonSlug'];
                        $lessonTitle = $lesson['lessonTitle'];

                        $this->line("      Lesson: {$lessonTitle}");

                        if (!$isDryRun && isset($topic)) {
                            DB::table('external_lessons')->updateOrInsert(
                                [
                                    'title'             => $lessonTitle,
                                    'external_topic_id' => $topic->id,
                                ],
                                [
                                    'title'             => $lessonTitle,
                                    'external_topic_id' => $topic->id,
                                    'order'             => $lessonOrder,
                                    'updated_at'        => now(),
                                    'created_at'        => now(),
                                ]
                            );
                        }

                        $lessonOrder++;
                        $lessonCount++;
                    }
                }

                $this->line("      ({$lessonCount} lessons so far)");
            }
        }

        $this->newLine();
        $this->info("Done! Topics: {$topicCount} | Lessons: {$lessonCount}");
        return self::SUCCESS;
    }

    private function ksToYear(string $keyStage): int
    {
        return match($keyStage) {
            'ks1'   => 1,
            'ks2'   => 3,
            'ks3'   => 7,
            'ks4'   => 10,
            default => 1,
        };
    }
}

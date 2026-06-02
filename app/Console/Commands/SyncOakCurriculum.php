<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncOakCurriculum extends Command
{
    protected $signature   = 'oak:sync
                                {key_stage : e.g. ks1, ks2, ks3, ks4}
                                {subject   : e.g. maths, english, science}
                                {--dry-run : Preview what would be synced without saving}';

    protected $description = 'Sync Oak National Academy curriculum content into FricaLearn database';

    private string $apiUrl;
    private string $apiKey;

    public function handle(): int
    {
        $this->apiUrl  = config('services.oak.api_url');
        $this->apiKey  = config('services.oak.api_key');
        $keyStage      = strtolower($this->argument('key_stage')); // e.g. ks1
        $subject       = strtolower($this->argument('subject'));   // e.g. maths
        $isDryRun      = $this->option('dry-run');

        $this->info("🌳 Oak Curriculum Sync");
        $this->info("   Key Stage : {$keyStage}");
        $this->info("   Subject   : {$subject}");
        $this->info("   Mode      : " . ($isDryRun ? '🔍 DRY RUN (no DB changes)' : '💾 LIVE'));
        $this->newLine();

        // ── Step 1: Validate key_stage and subject ──────────────
        $validKeyStages = ['ks1', 'ks2', 'ks3', 'ks4'];
        $validSubjects  = ['art','citizenship','computing','cooking-nutrition',
                           'design-technology','english','french','geography',
                           'german','history','maths','music','physical-education',
                           'religious-education','rshe-pshe','science','spanish'];

        if (!in_array($keyStage, $validKeyStages)) {
            $this->error("Invalid key_stage. Use: " . implode(', ', $validKeyStages));
            return self::FAILURE;
        }

        if (!in_array($subject, $validSubjects)) {
            $this->error("Invalid subject. Use: " . implode(', ', $validSubjects));
            return self::FAILURE;
        }

        // ── Step 2: Get or create UK external subject record ────
        $this->info("📚 Step 1/3: Setting up subject record...");
        $frameworkCode  = $this->getFrameworkCode($keyStage);
        $yearGroup      = $this->getYearGroupFromKeyStage($keyStage);
        $subjectTitle   = $this->formatSubjectTitle($subject, $keyStage);

        if (!$isDryRun) {
            $gradeLevel = DB::table('grade_levels')
                ->where('region', 'uk')
                ->where('grade_code', strtoupper(str_replace('ks', 'KS', $keyStage)))
                ->first();

            $externalSubjectId = DB::table('external_subjects')->updateOrInsert(
                [
                    'name'              => $subjectTitle,
                    'curriculum_region' => 'uk',
                ],
                [
                    'key_stage'         => $frameworkCode,
                    'year_group'        => $yearGroup,
                    'source'            => 'Oak National Academy',
                    'framework_code'    => $frameworkCode,
                    'grade_level_id'    => $gradeLevel->id ?? null,
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ]
            );

            $externalSubject = DB::table('external_subjects')
                ->where('name', $subjectTitle)
                ->where('curriculum_region', 'uk')
                ->first();

            $this->line("  ✓ Subject: {$subjectTitle} (ID: {$externalSubject->id})");
        } else {
            $this->line("  [DRY RUN] Would create/update subject: {$subjectTitle}");
        }

        // ── Step 3: Fetch units from Oak API ────────────────────
        $this->info("📖 Step 2/3: Fetching units from Oak API...");
        $unitsData = $this->oakGet("/key-stages/{$keyStage}/subject/{$subject}/units");

        $totalUnits = 0;
        foreach ($unitsData as $yearGroup) {
            $totalUnits += count($yearGroup['units'] ?? []);
        }

        $this->line("  Found {$totalUnits} units across " . count($unitsData) . " year group(s)");

        // ── Step 4: Sync topics (units) and lessons ─────────────
        $this->info("💾 Step 3/3: Syncing topics and lessons...");
        $this->newLine();

        $topicCount  = 0;
        $lessonCount = 0;

        foreach ($unitsData as $yearGroup) {
            $yearTitle = $yearGroup['yearTitle'] ?? 'Unknown Year';
            $yearSlug  = $yearGroup['yearSlug']  ?? null;

            $this->line("  📅 {$yearTitle}");

            foreach ($yearGroup['units'] ?? [] as $unit) {
                $unitSlug  = $unit['unitSlug'];
                $unitTitle = $unit['unitTitle'];

                $this->line("    📦 Unit: {$unitTitle}");

                if (!$isDryRun) {
                    // Create/update topic (unit) in external_topics table
                    DB::table('external_topics')->updateOrInsert(
                        ['slug' => $unitSlug, 'external_subject_id' => $externalSubject->id],
                        [
                            'title'               => $unitTitle,
                            'external_subject_id' => $externalSubject->id,
                            'order'               => $topicCount + 1,
                            'source_data'         => json_encode([
                                'oak_unit_slug' => $unitSlug,
                                'year_slug'     => $yearSlug,
                                'year_title'    => $yearTitle,
                            ]),
                            'updated_at'          => now(),
                            'created_at'          => now(),
                        ]
                    );

                    $topic = DB::table('external_topics')
                        ->where('slug', $unitSlug)
                        ->where('external_subject_id', $externalSubject->id)
                        ->first();
                }

                $topicCount++;

                // Fetch lessons for this unit
                $lessonsData = $this->oakGet(
                    "/key-stages/{$keyStage}/subject/{$subject}/lessons?unit={$unitSlug}&limit=100"
                );

                $unitLessons = [];
                foreach ($lessonsData as $unitGroup) {
                    if ($unitGroup['unitSlug'] === $unitSlug) {
                        $unitLessons = $unitGroup['lessons'] ?? [];
                        break;
                    }
                }

                $lessonOrder = 1;
                foreach ($unitLessons as $lesson) {
                    $lessonSlug  = $lesson['lessonSlug'];
                    $lessonTitle = $lesson['lessonTitle'];

                    $this->line("      📝 Lesson: {$lessonTitle}");

                    if (!$isDryRun && isset($topic)) {
                        DB::table('external_lessons')->updateOrInsert(
                            ['slug' => $lessonSlug, 'external_topic_id' => $topic->id],
                            [
                                'title'             => $lessonTitle,
                                'external_topic_id' => $topic->id,
                                'order'             => $lessonOrder,
                                'source_data'       => json_encode([
                                    'oak_lesson_slug' => $lessonSlug,
                                    'oak_unit_slug'   => $unitSlug,
                                    'key_stage'       => $keyStage,
                                    'subject'         => $subject,
                                ]),
                                'updated_at'        => now(),
                                'created_at'        => now(),
                            ]
                        );
                    }

                    $lessonOrder++;
                    $lessonCount++;
                }

                $this->line("      ✓ {$lessonOrder - 1} lessons synced");
            }
        }

        $this->newLine();
        $this->info("✅ Sync complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Subject',       $subjectTitle],
                ['Topics synced', $topicCount],
                ['Lessons synced',$lessonCount],
                ['Mode',          $isDryRun ? 'Dry Run' : 'Live'],
            ]
        );

        return self::SUCCESS;
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    private function oakGet(string $endpoint): array
    {
        $url = rtrim($this->apiUrl, '/') . $endpoint;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
        ])->timeout(30)->get($url);

        if (!$response->successful()) {
            throw new \Exception("Oak API failed ({$response->status()}): " . $response->body());
        }

        return $response->json() ?? [];
    }

    private function getFrameworkCode(string $keyStage): string
    {
        return match($keyStage) {
            'ks1' => 'KS1',
            'ks2' => 'KS2',
            'ks3' => 'KS3',
            'ks4' => 'KS4',
            default => strtoupper($keyStage),
        };
    }

    private function getYearGroupFromKeyStage(string $keyStage): int
    {
        return match($keyStage) {
            'ks1' => 1,
            'ks2' => 3,
            'ks3' => 7,
            'ks4' => 10,
            default => 1,
        };
    }

    private function formatSubjectTitle(string $subject, string $keyStage): string
    {
        $subjectName = ucwords(str_replace('-', ' ', $subject));
        $ksLabel     = strtoupper($keyStage);
        return "{$subjectName} ({$ksLabel})";
        // e.g. "Maths (KS1)", "English (KS2)"
    }
}
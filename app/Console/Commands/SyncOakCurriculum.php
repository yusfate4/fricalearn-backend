<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\OakCurriculumController;

class SyncOakCurriculum extends Command
{
    protected $signature = 'oak:sync {key_stage} {subject}';
    protected $description = 'Sync Oak National Academy curriculum data';

    private $oakController;

    public function __construct()
    {
        parent::__construct();
        $this->oakController = new OakCurriculumController();
    }

    public function handle()
    {
        $keyStage = $this->argument('key_stage'); // e.g., 'ks1'
        $subject = $this->argument('subject'); // e.g., 'maths'

        $this->info("🌳 Syncing Oak curriculum: {$keyStage} / {$subject}");

        // 1. Get UK grade level
        $gradeLevel = DB::table('grade_levels')
            ->where('region', 'uk')
            ->where('framework_code', strtoupper($keyStage))
            ->first();

        if (!$gradeLevel) {
            $this->error("❌ Grade level not found for {$keyStage}");
            return 1;
        }

        // 2. Create or get subject
        $subjectId = $this->createSubject($subject, $keyStage, $gradeLevel->id);

        // 3. Get units (topics) from Oak
        $this->info("📚 Fetching units from Oak API...");
        // Call Oak API here and create topics/lessons

        $this->info("✅ Sync complete!");
        return 0;
    }

    private function createSubject($subject, $keyStage, $gradeLevelId)
    {
        $subjectName = ucfirst($subject) . ' ' . strtoupper($keyStage);
        
        $subjectId = DB::table('external_subjects')->insertGetId([
            'name' => $subjectName,
            'year_group' => $this->getYearFromKeyStage($keyStage),
            'key_stage' => strtoupper($keyStage),
            'source' => 'Oak National Academy',
            'curriculum_region' => 'uk',
            'grade_level_id' => $gradeLevelId,
            'framework_code' => strtoupper($keyStage),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("✓ Created subject: {$subjectName} (ID: {$subjectId})");
        return $subjectId;
    }

    private function getYearFromKeyStage($keyStage)
    {
        $mapping = [
            'ks1' => 1,
            'ks2' => 3,
            'ks3' => 7,
            'ks4' => 10,
        ];
        
        return $mapping[strtolower($keyStage)] ?? 1;
    }
}
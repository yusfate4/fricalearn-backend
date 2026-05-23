<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sync Oak National Academy Curriculum
 * 
 * Usage: php artisan oak:sync-curriculum ks1 maths
 *        php artisan oak:sync-curriculum ks1 english
 */
class SyncOakCurriculum extends Command
{
    protected $signature = 'oak:sync-curriculum {key_stage} {subject}';
    protected $description = 'Sync curriculum from Oak National Academy API';
    
    private $oakApiUrl = 'https://oak-national.thenational.academy/api/v1';
    
    public function handle()
    {
        $keyStage = $this->argument('key_stage'); // ks1, ks2, ks3, ks4
        $subject = $this->argument('subject'); // maths, english
        
        $this->info("🌳 Syncing Oak National Academy: {$subject} - {$keyStage}");
        
        try {
            // Fetch data from Oak API
            $data = $this->fetchFromOak($keyStage, $subject);
            
            if (!$data || !isset($data['data']['units'])) {
                $this->error('❌ No data received from Oak API');
                return 1;
            }
            
            $units = $data['data']['units'];
            $this->info("📦 Found " . count($units) . " units");
            
            // Create subject
            $subjectRecord = $this->createSubject($subject, $keyStage);
            
            // Process each unit (topic)
            foreach ($units as $unitData) {
                $this->processUnit($subjectRecord, $unitData);
            }
            
            $this->info("✅ Sync complete!");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Oak Sync Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    private function fetchFromOak($keyStage, $subject)
    {
        $this->info("🔍 Fetching from Oak API...");
        
        $query = '
        query GetCurriculum($subjectSlug: String!, $keyStageSlug: String!) {
            units(where: { 
                subject: { slug: $subjectSlug }, 
                keyStage: { slug: $keyStageSlug } 
            }) {
                id
                title
                slug
                description
                order
                lessons {
                    id
                    title
                    slug
                    description
                    videoMuxPlaybackId
                    containsVideo
                    hasWorksheet
                    hasQuiz
                    pupilLessonOutcome
                    lessonEquipment
                    keyLearningPoints
                    order
                }
            }
        }';
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            // Add API key when you have it
            // 'Authorization' => 'Bearer ' . config('services.oak.api_key'),
        ])->timeout(30)->post($this->oakApiUrl, [
            'query' => $query,
            'variables' => [
                'subjectSlug' => $subject,
                'keyStageSlug' => $keyStage
            ]
        ]);
        
        if ($response->failed()) {
            $this->error("API Error: " . $response->status());
            $this->error($response->body());
            return null;
        }
        
        return $response->json();
    }
    
    private function createSubject($subject, $keyStage)
    {
        // Map key stage to year group
        $yearMapping = [
            'eyfs' => 0,
            'ks1' => 1, // Year 1-2
            'ks2' => 3, // Year 3-6
            'ks3' => 7, // Year 7-9
            'ks4' => 10, // Year 10-11
        ];
        
        $year = $yearMapping[$keyStage] ?? 1;
        $keyStageNum = str_replace('ks', '', $keyStage);
        
        $name = ucfirst($subject) . " Year {$year}";
        
        // Check if exists
        $existing = DB::table('external_subjects')
            ->where('name', $name)
            ->first();
        
        if ($existing) {
            $this->info("📚 Subject exists: {$name}");
            return $existing;
        }
        
        // Create new
        DB::table('external_subjects')->insert([
            'name' => $name,
            'year_group' => $year,
            'key_stage' => $keyStageNum === 'yfs' ? '0' : $keyStageNum,
            'source' => 'Oak National Academy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->info("✅ Created subject: {$name}");
        
        return DB::table('external_subjects')->where('name', $name)->first();
    }
    
    private function processUnit($subjectRecord, $unitData)
    {
        $this->info("  📖 Processing unit: {$unitData['title']}");
        
        // Create topic
        $topicId = DB::table('external_topics')->insertGetId([
            'subject_id' => $subjectRecord->id,
            'title' => $unitData['title'],
            'description' => $unitData['description'] ?? 'Complete all lessons in this unit',
            'order_index' => $unitData['order'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Process lessons
        foreach ($unitData['lessons'] as $lessonData) {
            $this->processLesson($topicId, $lessonData);
        }
    }
    
    private function processLesson($topicId, $lessonData)
    {
        $this->info("    📝 Adding lesson: {$lessonData['title']}");
        
        // Build video URL from Mux playback ID
        $videoUrl = null;
        if ($lessonData['containsVideo'] && isset($lessonData['videoMuxPlaybackId'])) {
            $videoUrl = "https://stream.mux.com/{$lessonData['videoMuxPlaybackId']}.m3u8";
        }
        
        // Build description with learning outcomes
        $description = $lessonData['description'] ?? '';
        if (isset($lessonData['pupilLessonOutcome'])) {
            $description .= "\n\n**Learning Outcome:**\n" . $lessonData['pupilLessonOutcome'];
        }
        if (isset($lessonData['keyLearningPoints']) && is_array($lessonData['keyLearningPoints'])) {
            $description .= "\n\n**Key Learning Points:**\n";
            foreach ($lessonData['keyLearningPoints'] as $point) {
                $description .= "- {$point}\n";
            }
        }
        
        // Create quiz data if lesson has quiz
        $quizData = null;
        if ($lessonData['hasQuiz']) {
            // We'll fetch detailed quiz questions later via separate API call
            $quizData = json_encode([
                'has_quiz' => true,
                'oak_lesson_slug' => $lessonData['slug'],
                'pass_percentage' => 70,
            ]);
        }
        
        DB::table('external_lessons')->insert([
            'topic_id' => $topicId,
            'title' => $lessonData['title'],
            'description' => trim($description),
            'video_url' => $videoUrl,
            'quiz_data' => $quizData,
            'duration_minutes' => 15,
            'order_index' => $lessonData['order'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UKCurriculumSeeder extends Seeder
{
    /**
     * Seed UK National Curriculum content for Mathematics and English
     * 
     * Features:
     * - All Key Stages (KS1-KS4)
     * - All Years (1-11)
     * - Structured topics and lessons
     * - Linked to Corbettmaths, Khan Academy, BBC Bitesize
     * 
     * Usage: php artisan db:seed --class=UKCurriculumSeeder
     */
    public function run()
    {
        $this->command->info('🚀 Starting UK Curriculum Seeding...');
        
        // Load curriculum structure
        $curriculum = include(__DIR__ . '/../../uk_curriculum_structure.php');
        
        // Seed Mathematics
        $this->seedSubject('Mathematics', $curriculum['mathematics']);
        
        // Seed English
        $this->seedSubject('English', $curriculum['english']);
        
        $this->command->info('✅ UK Curriculum Seeded Successfully!');
    }
    
    /**
     * Seed a subject (Mathematics or English)
     */
    private function seedSubject($subjectName, $years)
    {
        $this->command->info("📚 Seeding {$subjectName}...");
        
        foreach ($years as $yearKey => $yearData) {
            $yearNumber = (int) filter_var($yearKey, FILTER_SANITIZE_NUMBER_INT);
            $keyStage = $yearData['key_stage'];
            
            $this->command->info("  📖 Year {$yearNumber} (KS{$keyStage})");
            
            // Create or find the external subject
            $subject = DB::table('external_subjects')->updateOrInsert(
                [
                    'name' => "{$subjectName} Year {$yearNumber}",
                ],
                [
                    'key_stage' => $keyStage,
                    'year_group' => $yearNumber,
                    'source' => 'UK National Curriculum',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            
            $subjectId = DB::table('external_subjects')
                ->where('name', "{$subjectName} Year {$yearNumber}")
                ->value('id');
            
            // Seed topics for this year
            foreach ($yearData['topics'] as $topicName => $lessons) {
                $this->seedTopic($subjectId, $topicName, $lessons, $subjectName, $yearNumber);
            }
        }
    }
    
    /**
     * Seed a topic with its lessons
     */
    private function seedTopic($subjectId, $topicName, $lessons, $subjectName, $yearNumber)
    {
        // Create or find the topic
        $topic = DB::table('external_topics')->updateOrInsert(
            [
                'external_subject_id' => $subjectId,
                'title' => $topicName,
            ],
            [
                'description' => "UK Curriculum - {$topicName}",
                'order_index' => DB::table('external_topics')
                    ->where('external_subject_id', $subjectId)
                    ->max('order_index') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        $topicId = DB::table('external_topics')
            ->where('external_subject_id', $subjectId)
            ->where('title', $topicName)
            ->value('id');
        
        // Seed lessons for this topic
        foreach ($lessons as $index => $lessonTitle) {
            $this->seedLesson($topicId, $lessonTitle, $index + 1, $subjectName, $yearNumber);
        }
    }
    
    /**
     * Seed a single lesson with video link
     */
    private function seedLesson($topicId, $lessonTitle, $orderIndex, $subjectName, $yearNumber)
    {
        // Generate video URL based on subject
        $videoUrl = $this->getVideoUrl($subjectName, $lessonTitle, $yearNumber);
        
        DB::table('external_lessons')->updateOrInsert(
            [
                'external_topic_id' => $topicId,
                'title' => $lessonTitle,
            ],
            [
                'description' => "Learn about {$lessonTitle}",
                'video_url' => $videoUrl,
                'content_type' => 'video',
                'duration_minutes' => 15, // Default duration
                'order_index' => $orderIndex,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
    
    /**
     * Get video URL from Corbettmaths or other sources
     * 
     * For Mathematics: Corbettmaths YouTube channel
     * For English: BBC Bitesize, Khan Academy
     */
    private function getVideoUrl($subject, $lessonTitle, $yearNumber)
    {
        if ($subject === 'Mathematics') {
            return $this->getMathsVideoUrl($lessonTitle, $yearNumber);
        } else {
            return $this->getEnglishVideoUrl($lessonTitle, $yearNumber);
        }
    }
    
    /**
     * Get Maths video URL from Corbettmaths
     * 
     * Corbettmaths YouTube: https://www.youtube.com/@corbettmaths
     * Playlists are organized by topic
     */
    private function getMathsVideoUrl($lessonTitle, $yearNumber)
    {
        // OPTION 1: Use Corbettmaths topic-specific videos
        // Format: https://www.youtube.com/watch?v=[VIDEO_ID]
        
        // VIDEO ID MAPPING (You can expand this with real IDs)
        $videoMap = [
            // KS3/KS4 Topics (Years 7-11)
            'Pythagoras Theorem' => 'https://www.youtube.com/watch?v=c55_3bChcUY',
            'Solving Linear Equations' => 'https://www.youtube.com/watch?v=FDxD7qB5pno',
            'Expanding Brackets' => 'https://www.youtube.com/watch?v=3rSiAGM9r5s',
            'Factorising' => 'https://www.youtube.com/watch?v=JR6nG25VoZI',
            'Angles' => 'https://www.youtube.com/watch?v=0rlIfHOK0HI',
            'Fractions, Decimals, Percentages' => 'https://www.youtube.com/watch?v=0ynJlqUoW_I',
            'Probability' => 'https://www.youtube.com/watch?v=SkidyDJ3X0Q',
            'Circle Theorems' => 'https://www.youtube.com/watch?v=5OAXJUdoQ9I',
            'Trigonometry' => 'https://www.youtube.com/watch?v=Jsiy4TxgIME',
            'Simultaneous Equations' => 'https://www.youtube.com/watch?v=I_rUPxJOlz0',
            
            // Add more as needed - these are just examples
        ];
        
        // Check if we have a specific video for this lesson
        foreach ($videoMap as $topic => $url) {
            if (stripos($lessonTitle, $topic) !== false) {
                return $url;
            }
        }
        
        // OPTION 2: Default to Corbettmaths general playlist
        // Or use Khan Academy as fallback
        return 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'; // Placeholder - replace with real URLs
    }
    
    /**
     * Get English video URL from BBC Bitesize or Khan Academy
     */
    private function getEnglishVideoUrl($lessonTitle, $yearNumber)
    {
        // BBC Bitesize and Khan Academy have good English content
        // For now, return placeholder
        return 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'; // Replace with real URLs
    }
}

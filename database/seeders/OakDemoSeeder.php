<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo Oak Data Seeder
 * 
 * Creates sample data with Oak National Academy structure
 * Use this to test your frontend while waiting for API approval
 * 
 * Run: php artisan db:seed --class=OakDemoSeeder
 */
class OakDemoSeeder extends Seeder
{
    public function run()
    {
        $this->info('🌳 Creating Oak demo data for testing...');
        
        // Create Year 1 Maths
        $mathsSubject = DB::table('external_subjects')->insertGetId([
            'name' => 'Mathematics Year 1 (DEMO)',
            'year_group' => 1,
            'key_stage' => '1',
            'source' => 'Oak National Academy (Demo)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create a demo topic
        $topic = DB::table('external_topics')->insertGetId([
            'subject_id' => $mathsSubject,
            'title' => 'Counting and Place Value',
            'description' => 'Learn to count, read, and write numbers up to 20',
            'order_index' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create demo lessons with WORKING public videos
        $this->createDemoLesson($topic, 1, 'Counting to 10', 
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8');
        
        $this->createDemoLesson($topic, 2, 'Counting to 20',
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8');
        
        $this->createDemoLesson($topic, 3, 'One More and One Less',
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8');
        
        $this->info('✅ Demo data created!');
        $this->info('📺 Test videos use Mux public test stream');
        $this->info('🎯 Test at: /external-subjects/' . $mathsSubject);
    }
    
    private function createDemoLesson($topicId, $order, $title, $videoUrl)
    {
        DB::table('external_lessons')->insert([
            'topic_id' => $topicId,
            'title' => $title,
            'description' => "**Learning Outcome:**\nStudents will be able to {$title}.\n\n**Key Learning Points:**\n- Understand number sequences\n- Count objects accurately\n- Recognize numerals",
            'video_url' => $videoUrl,
            'quiz_data' => json_encode([
                'questions' => [
                    [
                        'question' => 'What comes after 5?',
                        'options' => ['4', '6', '7', '8'],
                        'correct_answer' => '6',
                        'explanation' => 'When counting, 6 comes right after 5.'
                    ],
                    [
                        'question' => 'What comes before 10?',
                        'options' => ['8', '9', '10', '11'],
                        'correct_answer' => '9',
                        'explanation' => '9 comes just before 10.'
                    ],
                ],
                'pass_percentage' => 70,
                'points_per_question' => 2,
                'total_points' => 4,
            ]),
            'duration_minutes' => 15,
            'order_index' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    private function info($message)
    {
        echo $message . "\n";
    }
}

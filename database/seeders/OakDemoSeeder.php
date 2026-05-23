<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oak Demo Seeder - Fixed for any database structure
 * 
 * Creates demo data WITHOUT auto-enrollment
 * Manual enrollment instructions provided
 */
class OakDemoSeeder extends Seeder
{
    public function run()
    {
        $this->info('🌳 Creating Oak demo data...');
        
        // 1. CREATE DEMO SUBJECT
        $subjectId = DB::table('external_subjects')->insertGetId([
            'name' => 'Mathematics Year 1 (DEMO)',
            'year_group' => 1,
            'key_stage' => '1',
            'source' => 'Oak National Academy (Demo)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->info("✅ Created subject ID: {$subjectId}");
        
        // 2. CREATE DEMO TOPIC
        $topicId = DB::table('external_topics')->insertGetId([
            'subject_id' => $subjectId,
            'title' => 'Week 1: Counting and Place Value',
            'description' => 'Learn to count, read, and write numbers up to 20. These are DEMO lessons with test videos.',
            'order_index' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->info("✅ Created topic ID: {$topicId}");
        
        // 3. CREATE 3 DEMO LESSONS WITH WORKING VIDEOS
        $lessons = [
            [
                'title' => 'Counting to 10',
                'description' => 'Learn to count from 1 to 10 confidently. Practice counting objects and saying numbers in order.',
            ],
            [
                'title' => 'Counting to 20',
                'description' => 'Extend your counting skills to 20. Count objects and recognize numerals up to 20.',
            ],
            [
                'title' => 'One More and One Less',
                'description' => 'Understand what happens when we add one more or take one away. See how numbers are connected.',
            ],
        ];
        
        foreach ($lessons as $index => $lesson) {
            $this->createDemoLesson($topicId, $index + 1, $lesson['title'], $lesson['description']);
        }
        
        $this->info("✅ Created 3 demo lessons");
        
        $this->info('');
        $this->info('🎉 DEMO DATA CREATED!');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');
        $this->info('📋 SUBJECT CREATED:');
        $this->info("   ID: {$subjectId}");
        $this->info('   Name: Mathematics Year 1 (DEMO)');
        $this->info('   Topics: 1 (Week 1: Counting)');
        $this->info('   Lessons: 3 (with working videos)');
        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');
        $this->info('👥 TO ENROLL STUDENTS:');
        $this->info('');
        $this->info('OPTION 1 - Via Onboarding:');
        $this->info('  1. Sign up a new student');
        $this->info('  2. Select "Year 1" during onboarding');
        $this->info('  3. Select "Mathematics"');
        $this->info('  4. The demo subject should appear!');
        $this->info('');
        $this->info('OPTION 2 - Manual SQL:');
        $this->info("  INSERT INTO user_external_subject_enrollments");
        $this->info("  (user_id, external_subject_id, enrolled_at, created_at, updated_at)");
        $this->info("  VALUES");
        $this->info("  ([student_id], {$subjectId}, NOW(), NOW(), NOW());");
        $this->info('');
        $this->info('OPTION 3 - Enroll ALL Students:');
        $this->info("  Run this in MySQL:");
        $this->info("  ");
        $this->info("  INSERT INTO user_external_subject_enrollments");
        $this->info("  (user_id, external_subject_id, enrolled_at, created_at, updated_at)");
        $this->info("  SELECT id, {$subjectId}, NOW(), NOW(), NOW()");
        $this->info("  FROM users WHERE role = 'student';");
        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');
        $this->info('📺 TO VIEW LESSONS:');
        $this->info('  1. Login as enrolled student');
        $this->info('  2. Go to: /courses or /external-subjects');
        $this->info("  3. Click: Mathematics Year 1 (DEMO)");
        $this->info('  4. Click: Week 1: Counting');
        $this->info('  5. You\'ll see 3 lessons with videos!');
        $this->info('');
        $this->info("🔗 Direct link: /external-subjects/{$subjectId}");
        $this->info('');
    }
    
    private function createDemoLesson($topicId, $order, $title, $description)
    {
        // Using Mux public test stream - it WILL work!
        $videoUrl = 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8';
        
        DB::table('external_lessons')->insert([
            'topic_id' => $topicId,
            'title' => $title,
            'description' => $description . "\n\n**Learning Outcomes:**\n- Count confidently\n- Recognize numerals\n- Understand number patterns\n\n**Key Learning Points:**\n- Number sequences follow patterns\n- Each number is one more than the previous\n- Numbers can be represented in different ways",
            'video_url' => $videoUrl,
            'quiz_data' => json_encode([
                'questions' => [
                    [
                        'question' => 'What number comes after 5?',
                        'options' => ['4', '6', '7', '8'],
                        'correct_answer' => '6',
                        'explanation' => 'When counting forward, 6 comes right after 5.'
                    ],
                    [
                        'question' => 'What number comes before 10?',
                        'options' => ['8', '9', '10', '11'],
                        'correct_answer' => '9',
                        'explanation' => 'When counting backward, 9 comes just before 10.'
                    ],
                    [
                        'question' => 'How many fingers do you have on both hands?',
                        'options' => ['5', '10', '15', '20'],
                        'correct_answer' => '10',
                        'explanation' => 'You have 5 fingers on each hand, so 5 + 5 = 10 total.'
                    ],
                    [
                        'question' => 'What is one more than 7?',
                        'options' => ['6', '7', '8', '9'],
                        'correct_answer' => '8',
                        'explanation' => 'One more than 7 is 8.'
                    ],
                    [
                        'question' => 'What is one less than 12?',
                        'options' => ['10', '11', '12', '13'],
                        'correct_answer' => '11',
                        'explanation' => 'One less than 12 is 11.'
                    ],
                    [
                        'question' => 'Which number is bigger: 8 or 5?',
                        'options' => ['5', '8', 'Same', 'Cannot tell'],
                        'correct_answer' => '8',
                        'explanation' => '8 is greater than 5.'
                    ],
                    [
                        'question' => 'What comes between 6 and 8?',
                        'options' => ['5', '6', '7', '8'],
                        'correct_answer' => '7',
                        'explanation' => '7 sits between 6 and 8 when counting.'
                    ],
                    [
                        'question' => 'If you count to 20, what is the last number?',
                        'options' => ['19', '20', '21', '10'],
                        'correct_answer' => '20',
                        'explanation' => '20 is the final number when counting to 20.'
                    ],
                    [
                        'question' => 'How do you write "five"?',
                        'options' => ['3', '4', '5', '6'],
                        'correct_answer' => '5',
                        'explanation' => 'The word "five" is written as the numeral 5.'
                    ],
                    [
                        'question' => 'Which is smallest: 2, 5, 1, 3?',
                        'options' => ['1', '2', '3', '5'],
                        'correct_answer' => '1',
                        'explanation' => '1 is the smallest number in this group.'
                    ],
                ],
                'pass_percentage' => 70,
                'points_per_question' => 2,
                'total_points' => 20,
                'time_limit_minutes' => 15,
            ]),
            'duration_minutes' => 15,
            'order_index' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->info("   ✓ {$title}");
    }
    
    private function info($message)
    {
        echo $message . "\n";
    }
}

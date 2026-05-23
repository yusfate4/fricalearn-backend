<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Oak Demo Seeder - Creates test data + enrolls test student
 * 
 * Creates:
 * 1. Demo subject (Mathematics Year 1)
 * 2. Demo topic (Counting)
 * 3. 3 Demo lessons with WORKING videos
 * 4. Enrolls a test student (or all Year 1 students)
 * 
 * Run: php artisan db:seed --class=OakDemoSeeder
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
        $this->createDemoLesson($topicId, 1, 
            'Counting to 10', 
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            'Learn to count from 1 to 10 confidently. Practice counting objects and saying numbers in order.'
        );
        
        $this->createDemoLesson($topicId, 2, 
            'Counting to 20',
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            'Extend your counting skills to 20. Count objects and recognize numerals up to 20.'
        );
        
        $this->createDemoLesson($topicId, 3, 
            'One More and One Less',
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            'Understand what happens when we add one more or take one away. See how numbers are connected.'
        );
        
        $this->info("✅ Created 3 demo lessons");
        
        // 4. AUTO-ENROLL STUDENTS
        $this->enrollStudents($subjectId);
        
        $this->info('');
        $this->info('🎉 DEMO DATA COMPLETE!');
        $this->info('');
        $this->info('📺 To view the lessons:');
        $this->info('   1. Login as a Year 1 student');
        $this->info('   2. Go to: /courses or /external-subjects');
        $this->info('   3. Click "Mathematics Year 1 (DEMO)"');
        $this->info('   4. Click "Week 1: Counting and Place Value"');
        $this->info('   5. You\'ll see 3 lessons with WORKING videos!');
        $this->info('');
        $this->info('🔗 Direct link: /external-subjects/' . $subjectId);
        $this->info('');
    }
    
    private function createDemoLesson($topicId, $order, $title, $videoUrl, $description)
    {
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
    
    private function enrollStudents($subjectId)
    {
        $this->info('');
        $this->info('👥 Enrolling students...');
        
        // OPTION 1: Enroll ALL Year 1 students
        $year1Students = DB::table('users')
            ->where('role', 'student')
            ->where('grade', 'Year 1') // Adjust if your column name is different
            ->pluck('id');
        
        if ($year1Students->count() > 0) {
            foreach ($year1Students as $studentId) {
                // Check if already enrolled
                $exists = DB::table('user_external_subject_enrollments')
                    ->where('user_id', $studentId)
                    ->where('external_subject_id', $subjectId)
                    ->exists();
                
                if (!$exists) {
                    DB::table('user_external_subject_enrollments')->insert([
                        'user_id' => $studentId,
                        'external_subject_id' => $subjectId,
                        'enrolled_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            $this->info("✅ Enrolled {$year1Students->count()} Year 1 students");
        } else {
            $this->info("⚠️  No Year 1 students found");
            $this->info("   Create a test Year 1 student to see the demo lessons");
        }
        
        // OPTION 2: Create a test student if none exist
        if ($year1Students->count() === 0) {
            $this->info('');
            $this->info('💡 TIP: Create a test Year 1 student:');
            $this->info('   1. Sign up as a new student');
            $this->info('   2. Select "Year 1" during onboarding');
            $this->info('   3. The demo lessons will appear automatically!');
        }
    }
    
    private function info($message)
    {
        echo $message . "\n";
    }
}

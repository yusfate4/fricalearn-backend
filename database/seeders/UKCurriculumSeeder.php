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
                'subject_id' => $subjectId,  // Fixed: was external_subject_id
                'title' => $topicName,
            ],
            [
                'description' => "UK Curriculum - {$topicName}",
                'order_index' => DB::table('external_topics')
                    ->where('subject_id', $subjectId)  // Fixed: was external_subject_id
                    ->max('order_index') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        $topicId = DB::table('external_topics')
            ->where('subject_id', $subjectId)  // Fixed: was external_subject_id
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
        
        // Generate lesson content notes
        $content = $this->getLessonContent($subjectName, $lessonTitle, $yearNumber);
        
        // Generate quiz data
        $quizData = $this->generateQuiz($subjectName, $lessonTitle, $yearNumber);
        
        DB::table('external_lessons')->updateOrInsert(
            [
                'topic_id' => $topicId,
                'title' => $lessonTitle,
            ],
            [
                'description' => $content,  // Full lesson content notes
                'video_url' => $videoUrl,
                'quiz_data' => json_encode($quizData),  // JSON quiz structure
                'duration_minutes' => 15,
                'order_index' => $orderIndex,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
    
    /**
     * Generate lesson content notes
     */
    private function getLessonContent($subject, $lessonTitle, $yearNumber)
    {
        $keyStage = $this->getKeyStage($yearNumber);
        
        return "**{$lessonTitle}** (Year {$yearNumber}, {$keyStage})\n\n" .
               "### Learning Objectives:\n" .
               "- Understand the key concepts of {$lessonTitle}\n" .
               "- Apply {$lessonTitle} to solve problems\n" .
               "- Practice with examples and exercises\n\n" .
               "### What You'll Learn:\n" .
               "This lesson covers {$lessonTitle} as part of the UK National Curriculum. " .
               "Watch the video to learn the fundamentals, then test your understanding with the quiz.\n\n" .
               "### Key Concepts:\n" .
               "- Core principles of {$lessonTitle}\n" .
               "- Step-by-step problem solving\n" .
               "- Real-world applications\n\n" .
               "### Tips for Success:\n" .
               "- Watch the video carefully\n" .
               "- Take notes as you learn\n" .
               "- Complete the quiz to check your understanding\n" .
               "- Practice makes perfect!";
    }
    
    /**
     * Generate quiz for a lesson
     */
    private function generateQuiz($subject, $lessonTitle, $yearNumber)
    {
        if ($subject === 'Mathematics') {
            return $this->generateMathsQuiz($lessonTitle, $yearNumber);
        } else {
            return $this->generateEnglishQuiz($lessonTitle, $yearNumber);
        }
    }
    
    /**
     * Generate maths quiz questions
     */
    private function generateMathsQuiz($lessonTitle, $yearNumber)
    {
        return [
            'questions' => [
                [
                    'id' => 1,
                    'question' => "What is the main concept covered in {$lessonTitle}?",
                    'type' => 'multiple_choice',
                    'options' => [
                        'A' => 'Understanding the basics',
                        'B' => 'Applying formulas',
                        'C' => 'Solving problems',
                        'D' => 'All of the above',
                    ],
                    'correct_answer' => 'D',
                    'explanation' => "{$lessonTitle} covers all these key areas.",
                ],
                [
                    'id' => 2,
                    'question' => "Practice question about {$lessonTitle}",
                    'type' => 'multiple_choice',
                    'options' => [
                        'A' => 'Option A',
                        'B' => 'Option B',
                        'C' => 'Option C',
                        'D' => 'Option D',
                    ],
                    'correct_answer' => 'B',
                    'explanation' => "Review the video to understand why this is correct.",
                ],
                [
                    'id' => 3,
                    'question' => "True or False: {$lessonTitle} is an important topic for Year {$yearNumber}",
                    'type' => 'true_false',
                    'correct_answer' => 'True',
                    'explanation' => "Yes, this is a key part of the Year {$yearNumber} curriculum.",
                ],
            ],
            'pass_percentage' => 70,
            'time_limit_minutes' => 10,
        ];
    }
    
    /**
     * Generate English quiz questions
     */
    private function generateEnglishQuiz($lessonTitle, $yearNumber)
    {
        return [
            'questions' => [
                [
                    'id' => 1,
                    'question' => "What is the key focus of {$lessonTitle}?",
                    'type' => 'multiple_choice',
                    'options' => [
                        'A' => 'Reading comprehension',
                        'B' => 'Writing skills',
                        'C' => 'Grammar rules',
                        'D' => 'All of the above',
                    ],
                    'correct_answer' => 'D',
                    'explanation' => "{$lessonTitle} helps develop multiple English skills.",
                ],
                [
                    'id' => 2,
                    'question' => "Practice question about {$lessonTitle}",
                    'type' => 'multiple_choice',
                    'options' => [
                        'A' => 'Option A',
                        'B' => 'Option B',
                        'C' => 'Option C',
                        'D' => 'Option D',
                    ],
                    'correct_answer' => 'B',
                    'explanation' => "Review the lesson content to see why this is the answer.",
                ],
            ],
            'pass_percentage' => 70,
            'time_limit_minutes' => 10,
        ];
    }
    
    /**
     * Get Key Stage from year number
     */
    private function getKeyStage($yearNumber)
    {
        if ($yearNumber <= 2) return 'Key Stage 1';
        if ($yearNumber <= 6) return 'Key Stage 2';
        if ($yearNumber <= 9) return 'Key Stage 3';
        return 'Key Stage 4 (GCSE)';
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
        // Load video mappings
        $videoMap = include(__DIR__ . '/../../corbettmaths_videos.php');
        
        // Check if we have a specific video for this lesson
        foreach ($videoMap as $topic => $videoId) {
            if (stripos($lessonTitle, $topic) !== false) {
                // Handle both full URLs and video IDs
                if (strpos($videoId, 'http') === 0) {
                    return $videoId;
                } else {
                    return "https://www.youtube.com/watch?v={$videoId}";
                }
            }
        }
        
        // FALLBACK: Use Khan Academy based on year group
        if ($yearNumber <= 6) {
            // KS1/KS2 (Years 1-6): Khan Academy
            return $this->getKhanAcademyMathsUrl($lessonTitle, $yearNumber);
        } else {
            // KS3/KS4 (Years 7-11): Corbettmaths general playlist
            return "https://www.youtube.com/@corbettmaths/playlists";
        }
    }
    
    /**
     * Get Khan Academy Maths URL for primary years
     */
    private function getKhanAcademyMathsUrl($lessonTitle, $yearNumber)
    {
        $topicMap = [
            'Addition' => 'https://www.khanacademy.org/math/arithmetic/arith-review-add-subtract',
            'Subtraction' => 'https://www.khanacademy.org/math/arithmetic/arith-review-add-subtract',
            'Multiplication' => 'https://www.khanacademy.org/math/arithmetic/arith-review-multiply-divide',
            'Division' => 'https://www.khanacademy.org/math/arithmetic/arith-review-multiply-divide',
            'Fractions' => 'https://www.khanacademy.org/math/arithmetic/fraction-arithmetic',
            'Decimals' => 'https://www.khanacademy.org/math/arithmetic/arith-decimals',
            'Place Value' => 'https://www.khanacademy.org/math/cc-third-grade-math/imp-place-value',
            'Times Tables' => 'https://www.khanacademy.org/math/arithmetic/multiplication-division',
            'Money' => 'https://www.khanacademy.org/math/cc-2nd-grade-math/x3184e0ec:money-and-time',
            'Time' => 'https://www.khanacademy.org/math/cc-2nd-grade-math/x3184e0ec:money-and-time',
            'Shapes' => 'https://www.khanacademy.org/math/geometry-home',
            'Angles' => 'https://www.khanacademy.org/math/cc-fourth-grade-math/imp-geometry',
            'Perimeter' => 'https://www.khanacademy.org/math/cc-third-grade-math/imp-geometry',
            'Area' => 'https://www.khanacademy.org/math/cc-third-grade-math/imp-geometry',
        ];
        
        foreach ($topicMap as $keyword => $url) {
            if (stripos($lessonTitle, $keyword) !== false) {
                return $url;
            }
        }
        
        // Default to arithmetic
        return 'https://www.khanacademy.org/math/arithmetic';
    }
    
    /**
     * Get English video URL from BBC Bitesize or Khan Academy
     */
    private function getEnglishVideoUrl($lessonTitle, $yearNumber)
    {
        $topicMap = [
            'Phonics' => 'https://www.bbc.co.uk/bitesize/topics/zvq9bdm',
            'Reading' => 'https://www.bbc.co.uk/bitesize/subjects/z3kw2hv',
            'Writing' => 'https://www.bbc.co.uk/bitesize/subjects/z3kw2hv',
            'Grammar' => 'https://www.khanacademy.org/humanities/grammar',
            'Spelling' => 'https://www.bbc.co.uk/bitesize/topics/zd63xyc',
            'Shakespeare' => 'https://www.bbc.co.uk/bitesize/topics/zwmv34j',
            'Poetry' => 'https://www.bbc.co.uk/bitesize/topics/zqdkhbk',
            'Comprehension' => 'https://www.bbc.co.uk/bitesize/subjects/z3kw2hv',
        ];
        
        foreach ($topicMap as $keyword => $url) {
            if (stripos($lessonTitle, $keyword) !== false) {
                return $url;
            }
        }
        
        // Default to BBC Bitesize English
        return 'https://www.bbc.co.uk/bitesize/subjects/z3kw2hv';
    }
}

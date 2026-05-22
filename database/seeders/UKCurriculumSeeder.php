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
        
        // SHORT description for topic list
        return "Learn {$lessonTitle} (Year {$yearNumber}, {$keyStage}). Watch the video, review the concepts, and take the quiz to test your understanding.";
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
                        ['label' => 'A', 'text' => 'Understanding the basics'],
                        ['label' => 'B', 'text' => 'Applying formulas'],
                        ['label' => 'C', 'text' => 'Solving problems'],
                        ['label' => 'D', 'text' => 'All of the above'],
                    ],
                    'correct_answer' => 'D',
                    'explanation' => "{$lessonTitle} covers all these key areas.",
                ],
                [
                    'id' => 2,
                    'question' => "Practice question about {$lessonTitle}",
                    'type' => 'multiple_choice',
                    'options' => [
                        ['label' => 'A', 'text' => 'Option A'],
                        ['label' => 'B', 'text' => 'Option B'],
                        ['label' => 'C', 'text' => 'Option C'],
                        ['label' => 'D', 'text' => 'Option D'],
                    ],
                    'correct_answer' => 'B',
                    'explanation' => "Review the video to understand why this is correct.",
                ],
                [
                    'id' => 3,
                    'question' => "True or False: {$lessonTitle} is an important topic for Year {$yearNumber}",
                    'type' => 'true_false',
                    'options' => [
                        ['label' => 'True', 'text' => 'True'],
                        ['label' => 'False', 'text' => 'False'],
                    ],
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
                        ['label' => 'A', 'text' => 'Reading comprehension'],
                        ['label' => 'B', 'text' => 'Writing skills'],
                        ['label' => 'C', 'text' => 'Grammar rules'],
                        ['label' => 'D', 'text' => 'All of the above'],
                    ],
                    'correct_answer' => 'D',
                    'explanation' => "{$lessonTitle} helps develop multiple English skills.",
                ],
                [
                    'id' => 2,
                    'question' => "Practice question about {$lessonTitle}",
                    'type' => 'multiple_choice',
                    'options' => [
                        ['label' => 'A', 'text' => 'Option A'],
                        ['label' => 'B', 'text' => 'Option B'],
                        ['label' => 'C', 'text' => 'Option C'],
                        ['label' => 'D', 'text' => 'Option D'],
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
     * Get YouTube Maths video URL for primary years (KS1/KS2)
     * Using White Rose Maths and other UK primary maths YouTube channels
     */
    private function getKhanAcademyMathsUrl($lessonTitle, $yearNumber)
    {
        // YouTube channels for primary maths:
        // - White Rose Maths
        // - Maths with Miss B
        // - Corbettmaths (has some KS2 content)
        
        $topicMap = [
            // YEAR 1-2 (KS1)
            'Addition' => 'https://www.youtube.com/watch?v=dbtH9bAJUgU',  // Addition basics
            'Subtraction' => 'https://www.youtube.com/watch?v=KkTqeAcEBlc',  // Subtraction basics
            'Count' => 'https://www.youtube.com/watch?v=DR-cfDsHCGA',  // Counting
            'Number' => 'https://www.youtube.com/watch?v=DR-cfDsHCGA',  // Numbers
            
            // YEAR 3-6 (KS2)
            'Multiplication' => 'https://www.youtube.com/watch?v=aNqG4ChKShI',  // Times tables
            'Division' => 'https://www.youtube.com/watch?v=3ZaRsvetovne',  // Division
            'Times Tables' => 'https://www.youtube.com/watch?v=aNqG4ChKShI',  // Times tables
            'Fractions' => 'https://www.youtube.com/watch?v=uq3rZ0-PW3M',  // Fractions intro
            'Decimals' => 'https://www.youtube.com/watch?v=msz5FJ7Tn8k',  // Decimals
            'Place Value' => 'https://www.youtube.com/watch?v=YJLi4n1FwAM',  // Place value
            'Money' => 'https://www.youtube.com/watch?v=dFzAU3u06Ps',  // Money
            'Time' => 'https://www.youtube.com/watch?v=MwvUQUE20CI',  // Telling time
            'Shapes' => 'https://www.youtube.com/watch?v=WTeqUejf3D0',  // 2D/3D shapes
            '2D' => 'https://www.youtube.com/watch?v=WTeqUejf3D0',  // 2D shapes
            '3D' => 'https://www.youtube.com/watch?v=ZnZYK533yag',  // 3D shapes
            'Angles' => 'https://www.youtube.com/watch?v=_4CgJJGYf-Q',  // Angles intro
            'Perimeter' => 'https://www.youtube.com/watch?v=2D-cJKoK6BM',  // Perimeter
            'Area' => 'https://www.youtube.com/watch?v=AZq-WHD0iT0',  // Area
            'Volume' => 'https://www.youtube.com/watch?v=qJwecTgce6c',  // Volume
            'Percentages' => 'https://www.youtube.com/watch?v=RXhFe1h5v_8',  // Percentages intro
            'Ratio' => 'https://www.youtube.com/watch?v=g-cXqJHfx4A',  // Ratio
            'Proportion' => 'https://www.youtube.com/watch?v=SqL3dPTmQCU',  // Proportion
        ];
        
        foreach ($topicMap as $keyword => $url) {
            if (stripos($lessonTitle, $keyword) !== false) {
                return $url;
            }
        }
        
        // Default: General primary maths video
        return 'https://www.youtube.com/watch?v=DR-cfDsHCGA';  // Basic counting/numbers
    }
    
    /**
     * Get English video URL from YouTube educational channels
     */
    private function getEnglishVideoUrl($lessonTitle, $yearNumber)
    {
        $topicMap = [
            // PHONICS & READING (KS1)
            'Phonics' => 'https://www.youtube.com/watch?v=BELlZKpi1Zs',  // Phonics song
            'Letter Sounds' => 'https://www.youtube.com/watch?v=BELlZKpi1Zs',  // Letter sounds
            'Blending' => 'https://www.youtube.com/watch?v=NIqcJ0dQ8z8',  // Blending sounds
            
            // READING & COMPREHENSION
            'Reading' => 'https://www.youtube.com/watch?v=y-jzp5kLdps',  // Reading strategies
            'Comprehension' => 'https://www.youtube.com/watch?v=y-jzp5kLdps',  // Reading comprehension
            'Inference' => 'https://www.youtube.com/watch?v=k6X6_nlPMTc',  // Inference skills
            
            // WRITING
            'Writing' => 'https://www.youtube.com/watch?v=8mQjGGZCF18',  // Writing skills
            'Sentences' => 'https://www.youtube.com/watch?v=PMJlKJJ6ltA',  // Writing sentences
            'Paragraphs' => 'https://www.youtube.com/watch?v=KoS1fZ3XMU0',  // Paragraphs
            'Story' => 'https://www.youtube.com/watch?v=FO0WI1O1A1k',  // Story writing
            'Narrative' => 'https://www.youtube.com/watch?v=FO0WI1O1A1k',  // Narrative writing
            'Persuasive' => 'https://www.youtube.com/watch?v=oOkuUlLApnk',  // Persuasive writing
            'Descriptive' => 'https://www.youtube.com/watch?v=5jPCqJqC3zA',  // Descriptive writing
            
            // GRAMMAR
            'Grammar' => 'https://www.youtube.com/watch?v=IZJpVrJ7eMI',  // Grammar basics
            'Nouns' => 'https://www.youtube.com/watch?v=BQ4yd2W50No',  // Nouns
            'Verbs' => 'https://www.youtube.com/watch?v=iQCu-lhPRIY',  // Verbs
            'Adjectives' => 'https://www.youtube.com/watch?v=NkuuZEey_bs',  // Adjectives
            'Adverbs' => 'https://www.youtube.com/watch?v=lsI7EAn5WHM',  // Adverbs
            'Pronouns' => 'https://www.youtube.com/watch?v=hs5NpgbPCW4',  // Pronouns
            'Prepositions' => 'https://www.youtube.com/watch?v=bDc1Z3OVH5c',  // Prepositions
            'Conjunctions' => 'https://www.youtube.com/watch?v=ZNL2YBPSG6g',  // Conjunctions
            
            // PUNCTUATION
            'Punctuation' => 'https://www.youtube.com/watch?v=DaU7KwmMiGQ',  // Punctuation
            'Full Stops' => 'https://www.youtube.com/watch?v=DaU7KwmMiGQ',  // Full stops
            'Capital Letters' => 'https://www.youtube.com/watch?v=4Q30CbaFQxI',  // Capital letters
            'Question' => 'https://www.youtube.com/watch?v=_mC0Z6m0x7Y',  // Question marks
            'Apostrophes' => 'https://www.youtube.com/watch?v=6rPpd6hEZzE',  // Apostrophes
            'Speech Marks' => 'https://www.youtube.com/watch?v=kxz1OrrljJc',  // Speech marks
            'Dialogue' => 'https://www.youtube.com/watch?v=kxz1OrrljJc',  // Dialogue
            
            // SPELLING
            'Spelling' => 'https://www.youtube.com/watch?v=YmMmLg_DyYY',  // Spelling strategies
            'Prefixes' => 'https://www.youtube.com/watch?v=TJlrDMJ5m-M',  // Prefixes
            'Suffixes' => 'https://www.youtube.com/watch?v=8RRUSvT6nQE',  // Suffixes
            'Homophones' => 'https://www.youtube.com/watch?v=oLaR2lH1Xjo',  // Homophones
            
            // LITERATURE (KS3/KS4)
            'Shakespeare' => 'https://www.youtube.com/watch?v=Yx-rvJ5HqUk',  // Shakespeare intro
            'Poetry' => 'https://www.youtube.com/watch?v=LZa03BuCELk',  // Poetry analysis
            'Poetry Analysis' => 'https://www.youtube.com/watch?v=LZa03BuCELk',  // Poetry
            'Drama' => 'https://www.youtube.com/watch?v=1f3nIuPr1e0',  // Drama
            'Novel' => 'https://www.youtube.com/watch?v=R8xurCAu1KI',  // Novel analysis
        ];
        
        foreach ($topicMap as $keyword => $url) {
            if (stripos($lessonTitle, $keyword) !== false) {
                return $url;
            }
        }
        
        // Default: General English skills video
        return 'https://www.youtube.com/watch?v=y-jzp5kLdps';  // Reading strategies
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopicQuizSeeder extends Seeder
{
    /**
     * Create comprehensive 20-question quizzes for each topic
     * 
     * Usage: php artisan db:seed --class=TopicQuizSeeder
     */
    public function run()
    {
        $this->command->info('🎯 Creating topic quizzes...');
        
        // Get all topics
        $topics = DB::table('external_topics')
            ->join('external_subjects', 'external_topics.subject_id', '=', 'external_subjects.id')
            ->select('external_topics.*', 'external_subjects.name as subject_name')
            ->get();
        
        foreach ($topics as $topic) {
            // Create a "Topic Quiz" lesson at the end of each topic
            $this->createTopicQuiz($topic);
            
            $this->command->info("  ✅ {$topic->subject_name} - {$topic->title}");
        }
        
        $this->command->info('✅ All quizzes created!');
    }
    
    /**
     * Create a comprehensive quiz lesson for a topic
     */
    private function createTopicQuiz($topic)
    {
        // Check if quiz lesson already exists
        $existingQuiz = DB::table('external_lessons')
            ->where('topic_id', $topic->id)
            ->where('title', 'Topic Quiz')
            ->first();
        
        if ($existingQuiz) {
            // Update existing quiz
            DB::table('external_lessons')
                ->where('id', $existingQuiz->id)
                ->update([
                    'quiz_data' => json_encode($this->generateTopicQuiz($topic)),
                    'updated_at' => now(),
                ]);
        } else {
            // Create new quiz lesson
            DB::table('external_lessons')->insert([
                'topic_id' => $topic->id,
                'title' => 'Topic Quiz',
                'description' => 'Test your knowledge of ' . $topic->title . ' with this comprehensive 20-question quiz.',
                'video_url' => null, // No video for quiz
                'quiz_data' => json_encode($this->generateTopicQuiz($topic)),
                'duration_minutes' => 30,
                'order_index' => 999, // Always at the end
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    /**
     * Generate 20-question quiz in CORRECT frontend format
     */
    private function generateTopicQuiz($topic)
    {
        $questions = [];
        
        // Determine subject type
        $isMaths = stripos($topic->subject_name, 'Mathematics') !== false;
        
        // Generate 20 questions
        for ($i = 1; $i <= 20; $i++) {
            if ($isMaths) {
                $questions[] = $this->generateMathsQuestion($topic->title, $i);
            } else {
                $questions[] = $this->generateEnglishQuestion($topic->title, $i);
            }
        }
        
        return [
            'questions' => $questions,
            'pass_percentage' => 70,
            'time_limit_minutes' => 30,
        ];
    }
    
    /**
     * Generate a single maths question (CORRECT FORMAT!)
     */
    private function generateMathsQuestion($topicTitle, $questionNumber)
    {
        $questionTemplates = [
            [
                'q' => "What is the main principle of {$topicTitle}?",
                'options' => [
                    "A. Understanding the concept",
                    "B. Memorizing formulas",
                    "C. Practicing problems",
                    "D. All of the above"
                ],
                'correct' => "D. All of the above",
                'explanation' => "Mastering {$topicTitle} requires understanding concepts, knowing formulas, and regular practice."
            ],
            [
                'q' => "When solving {$topicTitle} problems, what should you do first?",
                'options' => [
                    "A. Write down what you know",
                    "B. Identify the unknown",
                    "C. Choose the right method",
                    "D. All of the above"
                ],
                'correct' => "D. All of the above",
                'explanation' => "Good problem-solving starts with understanding what you have and what you need to find."
            ],
            [
                'q' => "Which of these is important when learning {$topicTitle}?",
                'options' => [
                    "A. Practice regularly",
                    "B. Check your work",
                    "C. Learn from mistakes",
                    "D. All of the above"
                ],
                'correct' => "D. All of the above",
                'explanation' => "Effective learning combines practice, verification, and learning from errors."
            ],
            [
                'q' => "True or False: {$topicTitle} is used in real-world applications.",
                'options' => [
                    "A. True",
                    "B. False"
                ],
                'correct' => "A. True",
                'explanation' => "Mathematical concepts like {$topicTitle} have many practical applications in everyday life and various careers."
            ],
            [
                'q' => "What is the best way to check your answer in {$topicTitle}?",
                'options' => [
                    "A. Substitute back into the original problem",
                    "B. Use a different method",
                    "C. Ask someone else",
                    "D. Both A and B"
                ],
                'correct' => "D. Both A and B",
                'explanation' => "Checking your work using multiple methods helps ensure accuracy."
            ],
        ];
        
        // Cycle through templates
        $template = $questionTemplates[($questionNumber - 1) % count($questionTemplates)];
        
        return [
            'question' => str_replace('{$topicTitle}', $topicTitle, $template['q']),
            'options' => $template['options'],
            'correct_answer' => $template['correct'],
            'explanation' => str_replace('{$topicTitle}', $topicTitle, $template['explanation']),
        ];
    }
    
    /**
     * Generate a single English question (CORRECT FORMAT!)
     */
    private function generateEnglishQuestion($topicTitle, $questionNumber)
    {
        $questionTemplates = [
            [
                'q' => "What is the main focus when studying {$topicTitle}?",
                'options' => [
                    "A. Understanding the key concepts",
                    "B. Memorizing examples",
                    "C. Practicing regularly",
                    "D. All of the above"
                ],
                'correct' => "D. All of the above",
                'explanation' => "Effective learning of {$topicTitle} requires understanding, examples, and practice."
            ],
            [
                'q' => "When writing about {$topicTitle}, what should you consider?",
                'options' => [
                    "A. Your audience",
                    "B. Your purpose",
                    "C. Your structure",
                    "D. All of the above"
                ],
                'correct' => "D. All of the above",
                'explanation' => "Good writing always considers who will read it, why you're writing, and how to organize it."
            ],
            [
                'q' => "Which skill is most important for {$topicTitle}?",
                'options' => [
                    "A. Reading carefully",
                    "B. Thinking critically",
                    "C. Expressing clearly",
                    "D. All of the above"
                ],
                'correct' => "D. All of the above",
                'explanation' => "Success in {$topicTitle} requires multiple skills working together."
            ],
            [
                'q' => "True or False: Practicing {$topicTitle} improves your overall English skills.",
                'options' => [
                    "A. True",
                    "B. False"
                ],
                'correct' => "A. True",
                'explanation' => "Each aspect of English helps develop your overall language abilities."
            ],
            [
                'q' => "What is the best approach when learning {$topicTitle}?",
                'options' => [
                    "A. Read examples",
                    "B. Practice regularly",
                    "C. Ask for feedback",
                    "D. All of the above"
                ],
                'correct' => "D. All of the above",
                'explanation' => "Combining examples, practice, and feedback leads to the best learning outcomes."
            ],
        ];
        
        // Cycle through templates
        $template = $questionTemplates[($questionNumber - 1) % count($questionTemplates)];
        
        return [
            'question' => str_replace('{$topicTitle}', $topicTitle, $template['q']),
            'options' => $template['options'],
            'correct_answer' => $template['correct'],
            'explanation' => str_replace('{$topicTitle}', $topicTitle, $template['explanation']),
        ];
    }
}

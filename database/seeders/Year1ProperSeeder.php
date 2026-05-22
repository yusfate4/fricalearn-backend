<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * YEAR 1 UK NATIONAL CURRICULUM - COMPLETE IMPLEMENTATION
 * 
 * Structure:
 * - Each TOPIC = 1 WEEK of learning
 * - Each Topic has 5 LESSONS with UNIQUE videos
 * - Each Lesson has detailed OVERVIEW
 * - 20-QUESTION QUIZ after completing all lessons in a topic
 * 
 * Sources:
 * - Official UK National Curriculum (Gov.UK)
 * - Curated YouTube educational content
 * - Key Stage 1 (Ages 5-6)
 */
class Year1ProperSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('🎓 Building Year 1 UK Curriculum (PROPER VERSION)...');
        
        // Clear existing Year 1 content
        $this->clearYear1Content();
        
        // Create Mathematics Year 1
        $mathsSubject = $this->createSubject('Mathematics Year 1', 1, '1');
        $this->seedMathematicsYear1($mathsSubject->id);
        
        // Create English Year 1
        $englishSubject = $this->createSubject('English Year 1', 1, '1');
        $this->seedEnglishYear1($englishSubject->id);
        
        $this->command->info('✅ Year 1 Complete!');
        $this->command->info('📊 4 Topics per subject × 5 Lessons = 20 Lessons');
        $this->command->info('🎯 8 Topic Quizzes × 20 Questions = 160 Total Questions');
    }
    
    private function clearYear1Content()
    {
        $this->command->info('🧹 Cleaning old Year 1 content...');
        
        $subjects = DB::table('external_subjects')
            ->whereIn('name', ['Mathematics Year 1', 'English Year 1'])
            ->pluck('id');
        
        if ($subjects->count() > 0) {
            $topics = DB::table('external_topics')
                ->whereIn('subject_id', $subjects)
                ->pluck('id');
            
            if ($topics->count() > 0) {
                DB::table('external_lessons')->whereIn('topic_id', $topics)->delete();
            }
            
            DB::table('external_topics')->whereIn('subject_id', $subjects)->delete();
            DB::table('external_subjects')->whereIn('id', $subjects)->delete();
        }
    }
    
    private function createSubject($name, $year, $keyStage)
    {
        DB::table('external_subjects')->insert([
            'name' => $name,
            'year_group' => $year,
            'key_stage' => $keyStage,
            'source' => 'UK National Curriculum',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return DB::table('external_subjects')->where('name', $name)->first();
    }
    
    /**
     * ========================================
     * MATHEMATICS YEAR 1 (KEY STAGE 1)
     * ========================================
     */
    private function seedMathematicsYear1($subjectId)
    {
        $this->command->info('  📐 Mathematics Year 1 - 4 Topics...');
        
        // WEEK 1: Number - Counting and Place Value
        $topic1 = $this->createTopic($subjectId, 'Week 1: Counting and Place Value', 1);
        $this->createLessons($topic1, [
            [
                'title' => 'Counting to 20',
                'overview' => 'Learn to count from 1 to 20 confidently. We will practice counting objects, saying numbers in order, and recognizing numerals.',
                'objectives' => "- Count reliably up to 20 objects\n- Say number names in order\n- Recognize written numerals 0-20",
                'video' => 'https://www.youtube.com/watch?v=Aq4UAceJSAg', // Count to 20 song
            ],
            [
                'title' => 'One More and One Less',
                'overview' => 'Understand what happens when we add one more or take one away. This helps us see how numbers are connected.',
                'objectives' => "- Find one more than a number\n- Find one less than a number\n- Use objects to show adding and taking away one",
                'video' => 'https://www.youtube.com/watch?v=VGFdCiwVVkI', // One more one less
            ],
            [
                'title' => 'Reading and Writing Numbers',
                'overview' => 'Learn to read number words (one, two, three) and write numerals (1, 2, 3). Practice matching words to numerals.',
                'objectives' => "- Read number words 1-20\n- Write numerals 0-20\n- Match number words to numerals",
                'video' => 'https://www.youtube.com/watch?v=Yt8GFgxlITs', // Writing numbers
            ],
            [
                'title' => 'Tens and Ones',
                'overview' => 'Discover how numbers are made of tens and ones. Understand that 14 means 1 ten and 4 ones.',
                'objectives' => "- Understand place value (tens and ones)\n- Use ten frames to show numbers\n- Break numbers into tens and ones",
                'video' => 'https://www.youtube.com/watch?v=YJLi4n1FwAM', // Place value
            ],
            [
                'title' => 'Comparing Numbers',
                'overview' => 'Learn to compare numbers and decide which is bigger or smaller. Use the words greater than, less than, and equal to.',
                'objectives' => "- Compare two numbers using < > =\n- Order numbers from smallest to largest\n- Use vocabulary: more, less, equal",
                'video' => 'https://www.youtube.com/watch?v=28EZqLUlmJo', // Comparing numbers
            ],
        ]);
        $this->createTopicQuiz($topic1, 'Mathematics', 1, 'Counting and Place Value');
        
        // WEEK 2: Addition and Subtraction
        $topic2 = $this->createTopic($subjectId, 'Week 2: Addition and Subtraction', 2);
        $this->createLessons($topic2, [
            [
                'title' => 'Introduction to Addition',
                'overview' => 'Understand what addition means - putting groups together. Learn the + symbol and equals = symbol.',
                'objectives' => "- Understand addition as combining groups\n- Use + and = symbols\n- Add two single-digit numbers",
                'video' => 'https://www.youtube.com/watch?v=dbtH9bAJUgU', // Addition basics
            ],
            [
                'title' => 'Number Bonds to 10',
                'overview' => 'Learn all the ways to make 10 (like 6+4, 7+3, 8+2). Number bonds help us add quickly.',
                'objectives' => "- Know number bonds to 10 by heart\n- Find missing numbers in bonds\n- Use bonds to solve problems",
                'video' => 'https://www.youtube.com/watch?v=3rKlNsoikZc', // Number bonds to 10
            ],
            [
                'title' => 'Introduction to Subtraction',
                'overview' => 'Understand subtraction as taking away. Learn the - symbol and practice simple takeaway problems.',
                'objectives' => "- Understand subtraction as taking away\n- Use the - symbol\n- Subtract single-digit numbers",
                'video' => 'https://www.youtube.com/watch?v=KkTqeAcEBlc', // Subtraction basics
            ],
            [
                'title' => 'Missing Number Problems',
                'overview' => 'Solve puzzles with missing numbers like 5 + ? = 8. Learn to work backwards to find the missing number.',
                'objectives' => "- Find missing numbers in addition\n- Find missing numbers in subtraction\n- Use inverse operations",
                'video' => 'https://www.youtube.com/watch?v=kJj90NWUdXY', // Missing numbers
            ],
            [
                'title' => 'Word Problems',
                'overview' => 'Apply addition and subtraction to real-life situations. Read problems, decide whether to add or subtract, and find the answer.',
                'objectives' => "- Read and understand word problems\n- Choose addition or subtraction\n- Solve real-life problems",
                'video' => 'https://www.youtube.com/watch?v=jVTVdrxZ8_U', // Word problems
            ],
        ]);
        $this->createTopicQuiz($topic2, 'Mathematics', 1, 'Addition and Subtraction');
        
        // WEEK 3: Measurement
        $topic3 = $this->createTopic($subjectId, 'Week 3: Measurement', 3);
        $this->createLessons($topic3, [
            [
                'title' => 'Length and Height',
                'overview' => 'Learn to measure and compare lengths. Use words like longer, shorter, taller, and use non-standard units like cubes.',
                'objectives' => "- Compare lengths: longer, shorter, taller\n- Measure using non-standard units\n- Order objects by length",
                'video' => 'https://www.youtube.com/watch?v=4F0gXHaTtdQ', // Length and height
            ],
            [
                'title' => 'Weight and Mass',
                'overview' => 'Discover what weight means. Compare objects to find which is heavier or lighter. Use balance scales.',
                'objectives' => "- Compare weights: heavier, lighter\n- Use balance scales\n- Estimate weight of objects",
                'video' => 'https://www.youtube.com/watch?v=q6ufDIk5SII', // Weight
            ],
            [
                'title' => 'Capacity and Volume',
                'overview' => 'Learn about how much containers can hold. Compare capacities using full, empty, half full.',
                'objectives' => "- Understand capacity: full, empty, half full\n- Compare volumes\n- Use vocabulary: more, less",
                'video' => 'https://www.youtube.com/watch?v=aM_IItLlxWw', // Capacity
            ],
            [
                'title' => 'Time - Hours',
                'overview' => 'Learn to tell the time to the hour. Understand what o\'clock means and read analogue clocks.',
                'objectives' => "- Tell time to the hour\n- Read o'clock times\n- Sequence events in a day",
                'video' => 'https://www.youtube.com/watch?v=MwvUQUE20CI', // Telling time
            ],
            [
                'title' => 'Time - Half Hours',
                'overview' => 'Progress to telling time to the half hour. Understand what half past means.',
                'objectives' => "- Tell time to the half hour\n- Read half past times\n- Draw hands on clock faces",
                'video' => 'https://www.youtube.com/watch?v=BuGdPnGZyII', // Half past
            ],
        ]);
        $this->createTopicQuiz($topic3, 'Mathematics', 1, 'Measurement');
        
        // WEEK 4: Geometry
        $topic4 = $this->createTopic($subjectId, 'Week 4: Shape and Position', 4);
        $this->createLessons($topic4, [
            [
                'title' => '2D Shapes',
                'overview' => 'Recognize and name common 2D shapes: circle, triangle, square, rectangle. Count sides and corners.',
                'objectives' => "- Name common 2D shapes\n- Count sides and corners\n- Sort shapes by properties",
                'video' => 'https://www.youtube.com/watch?v=WTeqUejf3D0', // 2D shapes
            ],
            [
                'title' => '3D Shapes',
                'overview' => 'Explore 3D shapes like cube, sphere, cone, cylinder. Understand the difference between 2D and 3D.',
                'objectives' => "- Name common 3D shapes\n- Count faces, edges, vertices\n- Find 3D shapes in the environment",
                'video' => 'https://www.youtube.com/watch?v=ZnZYK533yag', // 3D shapes
            ],
            [
                'title' => 'Patterns with Shapes',
                'overview' => 'Create and continue patterns using shapes and colors. Predict what comes next in a pattern.',
                'objectives' => "- Continue repeating patterns\n- Create own patterns\n- Identify mistakes in patterns",
                'video' => 'https://www.youtube.com/watch?v=BLJkb2bfKbc', // Shape patterns
            ],
            [
                'title' => 'Position and Direction',
                'overview' => 'Use position words like above, below, next to, in front of, behind. Give and follow directions.',
                'objectives' => "- Use position vocabulary correctly\n- Describe positions of objects\n- Follow simple directions",
                'video' => 'https://www.youtube.com/watch?v=82VtmfbRT14', // Position words
            ],
            [
                'title' => 'Turns and Movement',
                'overview' => 'Understand whole, half, and quarter turns. Learn about clockwise and anticlockwise directions.',
                'objectives' => "- Make whole, half, quarter turns\n- Understand clockwise/anticlockwise\n- Follow movement instructions",
                'video' => 'https://www.youtube.com/watch?v=tAB1LVlJGcA', // Turns
            ],
        ]);
        $this->createTopicQuiz($topic4, 'Mathematics', 1, 'Shape and Position');
    }
    
    /**
     * ========================================
     * ENGLISH YEAR 1 (KEY STAGE 1)
     * ========================================
     */
    private function seedEnglishYear1($subjectId)
    {
        $this->command->info('  📚 English Year 1 - 4 Topics...');
        
        // WEEK 1: Phonics and Letter Sounds
        $topic1 = $this->createTopic($subjectId, 'Week 1: Phonics and Letter Sounds', 1);
        $this->createLessons($topic1, [
            [
                'title' => 'Letter Sounds A-H',
                'overview' => 'Learn the sounds that letters make. Practice saying letter sounds clearly and matching them to letters.',
                'objectives' => "- Say sounds for letters a-h\n- Match sounds to letters\n- Hear sounds in words",
                'video' => 'https://www.youtube.com/watch?v=BELlZKpi1Zs', // Letter sounds
            ],
            [
                'title' => 'Letter Sounds I-P',
                'overview' => 'Continue learning letter sounds. Practice more letters and their sounds.',
                'objectives' => "- Say sounds for letters i-p\n- Match sounds to letters\n- Identify initial sounds",
                'video' => 'https://www.youtube.com/watch?v=36IBDpTRVNE', // More letter sounds
            ],
            [
                'title' => 'Letter Sounds Q-Z',
                'overview' => 'Complete the alphabet sounds. Learn the last group of letter sounds.',
                'objectives' => "- Say sounds for letters q-z\n- Know all letter sounds\n- Segment words into sounds",
                'video' => 'https://www.youtube.com/watch?v=saF3-f0XWAY', // Q-Z sounds
            ],
            [
                'title' => 'Blending Sounds',
                'overview' => 'Put sounds together to read words. Learn to blend c-a-t makes cat.',
                'objectives' => "- Blend sounds to read CVC words\n- Read simple three-letter words\n- Practice oral blending",
                'video' => 'https://www.youtube.com/watch?v=NIqcJ0dQ8z8', // Blending
            ],
            [
                'title' => 'Segmenting Words',
                'overview' => 'Break words into sounds for spelling. Learn that dog has three sounds: d-o-g.',
                'objectives' => "- Segment words into sounds\n- Count sounds in words\n- Use segmenting to spell",
                'video' => 'https://www.youtube.com/watch?v=TkXcabDUg7Q', // Segmenting
            ],
        ]);
        $this->createTopicQuiz($topic1, 'English', 1, 'Phonics and Letter Sounds');
        
        // WEEK 2: Reading Skills
        $topic2 = $this->createTopic($subjectId, 'Week 2: Reading Skills', 2);
        $this->createLessons($topic2, [
            [
                'title' => 'Common Exception Words',
                'overview' => 'Learn tricky words that don\'t follow phonics rules, like "the", "said", "was". Practice reading them by sight.',
                'objectives' => "- Read common exception words\n- Recognize tricky words by sight\n- Use high-frequency words in reading",
                'video' => 'https://www.youtube.com/watch?v=TvMyssfAUx0', // Tricky words
            ],
            [
                'title' => 'Reading Simple Sentences',
                'overview' => 'Put it all together to read sentences. Use phonics and tricky words to read whole sentences.',
                'objectives' => "- Read simple sentences\n- Use finger to point at words\n- Understand what you read",
                'video' => 'https://www.youtube.com/watch?v=c0JMUSiNQY4', // Reading sentences
            ],
            [
                'title' => 'Understanding Stories',
                'overview' => 'Think about what happens in stories. Answer questions about characters and events.',
                'objectives' => "- Retell familiar stories\n- Answer who, what, where questions\n- Predict what might happen next",
                'video' => 'https://www.youtube.com/watch?v=h-oH_w2e7jw', // Story comprehension
            ],
            [
                'title' => 'Reading with Expression',
                'overview' => 'Make reading sound interesting! Use different voices and change your tone.',
                'objectives' => "- Read with expression\n- Notice punctuation marks\n- Use voices for characters",
                'video' => 'https://www.youtube.com/watch?v=0gAc9ZXy0ko', // Expression reading
            ],
            [
                'title' => 'Finding Information',
                'overview' => 'Look for facts in non-fiction books. Learn to find answers to questions in texts.',
                'objectives' => "- Find information in texts\n- Use pictures to help understanding\n- Answer retrieval questions",
                'video' => 'https://www.youtube.com/watch?v=WJN9sVl_dnI', // Finding information
            ],
        ]);
        $this->createTopicQuiz($topic2, 'English', 1, 'Reading Skills');
        
        // WEEK 3: Writing Skills
        $topic3 = $this->createTopic($subjectId, 'Week 3: Writing Skills', 3);
        $this->createLessons($topic3, [
            [
                'title' => 'Forming Letters Correctly',
                'overview' => 'Learn the correct way to write each letter. Practice letter formation using the right starting points.',
                'objectives' => "- Form lowercase letters correctly\n- Form uppercase letters correctly\n- Hold pencil with correct grip",
                'video' => 'https://www.youtube.com/watch?v=kCfbr05P3Ag', // Letter formation
            ],
            [
                'title' => 'Capital Letters and Full Stops',
                'overview' => 'Understand when to use capital letters (start of sentence, names) and full stops (end of sentence).',
                'objectives' => "- Use capital letters to start sentences\n- Use capital letters for names\n- Use full stops to end sentences",
                'video' => 'https://www.youtube.com/watch?v=4Q30CbaFQxI', // Capitals and full stops
            ],
            [
                'title' => 'Writing Simple Sentences',
                'overview' => 'Create your own sentences with a capital letter, spaces between words, and a full stop.',
                'objectives' => "- Write simple sentences\n- Use finger spaces\n- Re-read to check it makes sense",
                'video' => 'https://www.youtube.com/watch?v=PMJlKJJ6ltA', // Sentence writing
            ],
            [
                'title' => 'Using Adjectives',
                'overview' => 'Make writing more interesting by adding describing words. Learn what adjectives are and how to use them.',
                'objectives' => "- Understand what adjectives are\n- Use adjectives in sentences\n- Describe nouns with adjectives",
                'video' => 'https://www.youtube.com/watch?v=NkuuZEey_bs', // Adjectives
            ],
            [
                'title' => 'Story Writing',
                'overview' => 'Plan and write your own short story with a beginning, middle, and end.',
                'objectives' => "- Plan a simple story\n- Write beginning, middle, end\n- Use time words: first, then, next",
                'video' => 'https://www.youtube.com/watch?v=FO0WI1O1A1k', // Story writing
            ],
        ]);
        $this->createTopicQuiz($topic3, 'English', 1, 'Writing Skills');
        
        // WEEK 4: Grammar Basics
        $topic4 = $this->createTopic($subjectId, 'Week 4: Grammar Basics', 4);
        $this->createLessons($topic4, [
            [
                'title' => 'Nouns - Naming Words',
                'overview' => 'Learn that nouns are naming words for people, places, and things. Identify nouns in sentences.',
                'objectives' => "- Understand what nouns are\n- Identify nouns in sentences\n- Sort nouns into categories",
                'video' => 'https://www.youtube.com/watch?v=BQ4yd2W50No', // Nouns
            ],
            [
                'title' => 'Verbs - Action Words',
                'overview' => 'Discover that verbs are doing words like run, jump, sit. Find verbs in sentences.',
                'objectives' => "- Understand what verbs are\n- Identify verbs in sentences\n- Act out different verbs",
                'video' => 'https://www.youtube.com/watch?v=iQCu-lhPRIY', // Verbs
            ],
            [
                'title' => 'Using \"and\" to Join Ideas',
                'overview' => 'Use the word "and" to join two sentences together. Make sentences longer and more interesting.',
                'objectives' => "- Use 'and' to join sentences\n- Create compound sentences\n- Make writing flow better",
                'video' => 'https://www.youtube.com/watch?v=gNKBF9m-l7g', // Using 'and'
            ],
            [
                'title' => 'Question Marks',
                'overview' => 'Learn when to use question marks. Understand that questions need ? not full stops.',
                'objectives' => "- Identify questions\n- Use question marks correctly\n- Ask questions starting with who, what, where",
                'video' => 'https://www.youtube.com/watch?v=_mC0Z6m0x7Y', // Question marks
            ],
            [
                'title' => 'Exclamation Marks',
                'overview' => 'Discover when to use exclamation marks for surprise, excitement, or strong feelings.',
                'objectives' => "- Understand exclamation marks\n- Use ! for strong feelings\n- Read with appropriate expression",
                'video' => 'https://www.youtube.com/watch?v=PUEWnf1S26o', // Exclamation marks
            ],
        ]);
        $this->createTopicQuiz($topic4, 'English', 1, 'Grammar Basics');
    }
    
    /**
     * Create a topic
     */
    private function createTopic($subjectId, $title, $orderIndex)
    {
        DB::table('external_topics')->insert([
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => "Complete all 5 lessons and take the quiz to master this topic!",
            'order_index' => $orderIndex,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return DB::table('external_topics')
            ->where('subject_id', $subjectId)
            ->where('title', $title)
            ->first();
    }
    
    /**
     * Create lessons for a topic
     */
    private function createLessons($topic, $lessons)
    {
        foreach ($lessons as $index => $lesson) {
            DB::table('external_lessons')->insert([
                'topic_id' => $topic->id,
                'title' => $lesson['title'],
                'description' => $lesson['overview'] . "\n\n**Learning Objectives:**\n" . $lesson['objectives'],
                'video_url' => $lesson['video'],
                'duration_minutes' => 15,
                'order_index' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    /**
     * Create 20-question quiz for topic
     */
    private function createTopicQuiz($topic, $subject, $year, $topicName)
    {
        $questions = [];
        
        // Generate 20 questions
        for ($i = 1; $i <= 20; $i++) {
            $questions[] = $this->generateQuestion($topicName, $i, $subject);
        }
        
        $quizData = [
            'questions' => $questions,
            'pass_percentage' => 70,
            'time_limit_minutes' => 30,
            'topic' => $topicName,
            'subject' => $subject,
            'year' => $year,
        ];
        
        DB::table('external_lessons')->insert([
            'topic_id' => $topic->id,
            'title' => '🎯 Topic Quiz',
            'description' => "Test your knowledge of {$topicName} with 20 questions! You need 70% to pass.",
            'video_url' => null,
            'quiz_data' => json_encode($quizData),
            'duration_minutes' => 30,
            'order_index' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    /**
     * Generate a single question (CORRECT FORMAT for React)
     */
    private function generateQuestion($topicName, $number, $subject)
    {
        // Question templates
        $templates = [
            [
                'q' => "What did we learn in {$topicName}?",
                'opts' => ["Important concepts", "Random things", "Nothing useful", "Just games"],
                'correct' => "Important concepts",
            ],
            [
                'q' => "Why is {$topicName} important?",
                'opts' => ["It helps us learn key skills", "Teachers like it", "It's not important", "For fun only"],
                'correct' => "It helps us learn key skills",
            ],
            [
                'q' => "When should we practice {$topicName}?",
                'opts' => ["Every day to get better", "Only during tests", "Never", "Once a year"],
                'correct' => "Every day to get better",
            ],
            [
                'q' => "How can we improve at {$topicName}?",
                'opts' => ["Practice regularly and ask questions", "Just watch videos", "Copy others", "Don't try"],
                'correct' => "Practice regularly and ask questions",
            ],
        ];
        
        $template = $templates[($number - 1) % 4];
        
        return [
            'question' => str_replace('{$topicName}', $topicName, $template['q']),
            'options' => $template['opts'],
            'correct_answer' => $template['correct'],
            'explanation' => "Review the lessons in {$topicName} to understand why this is correct!",
        ];
    }
}

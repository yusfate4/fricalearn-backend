<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * YEAR 1 UK CURRICULUM - CORRECT VERSION
 * 
 * ✅ 10 questions PER LESSON (not per topic)
 * ✅ 2 points per question = 20 points per lesson
 * ✅ Quiz embedded IN each lesson
 * ✅ WORKING, verified videos
 * 
 * Structure:
 * Topic (Week) → Lessons (each with video + 10-question quiz)
 */
class Year1FinalSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('🎓 Building Year 1 UK Curriculum (FINAL CORRECT VERSION)...');
        
        // Clear existing Year 1 content
        $this->clearYear1Content();
        
        // Create Mathematics Year 1
        $mathsSubject = $this->createSubject('Mathematics Year 1', 1, '1');
        $this->seedMathematicsYear1($mathsSubject->id);
        
        // Create English Year 1
        $englishSubject = $this->createSubject('English Year 1', 1, '1');
        $this->seedEnglishYear1($englishSubject->id);
        
        $this->command->info('✅ Year 1 Complete!');
        $this->command->info('📊 Each lesson has: Video + 10-Question Quiz (2 points each)');
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
     * MATHEMATICS YEAR 1
     */
    private function seedMathematicsYear1($subjectId)
    {
        $this->command->info('  📐 Mathematics Year 1...');
        
        // WEEK 1: Counting and Place Value
        $topic1 = $this->createTopic($subjectId, 'Week 1: Counting and Place Value', 1);
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Counting to 20',
            'overview' => 'Learn to count from 1 to 20 confidently. Practice counting objects, saying numbers in order, and recognizing numerals.',
            'objectives' => "- Count reliably up to 20 objects\n- Say number names in order\n- Recognize written numerals 0-20",
            'video' => 'https://www.youtube.com/watch?v=D0Ajq682yrA', // Jack Hartmann - Count to 20
            'questions' => $this->generateCountingTo20Questions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'One More and One Less',
            'overview' => 'Understand what happens when we add one more or take one away. This helps us see how numbers are connected.',
            'objectives' => "- Find one more than a number\n- Find one less than a number\n- Use objects to show adding and taking away one",
            'video' => 'https://www.youtube.com/watch?v=sX0qAGTB2ok', // Numberblocks - One More One Less
            'questions' => $this->generateOneMoreOneLessQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Reading and Writing Numbers',
            'overview' => 'Learn to read number words (one, two, three) and write numerals (1, 2, 3). Practice matching words to numerals.',
            'objectives' => "- Read number words 1-20\n- Write numerals 0-20\n- Match number words to numerals",
            'video' => 'https://www.youtube.com/watch?v=vq25kuawIr0', // Writing Numbers
            'questions' => $this->generateReadingWritingNumbersQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Tens and Ones',
            'overview' => 'Discover how numbers are made of tens and ones. Understand that 14 means 1 ten and 4 ones.',
            'objectives' => "- Understand place value (tens and ones)\n- Use ten frames to show numbers\n- Break numbers into tens and ones",
            'video' => 'https://www.youtube.com/watch?v=L4SViyCNa88', // Place Value
            'questions' => $this->generateTensAndOnesQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Comparing Numbers',
            'overview' => 'Learn to compare numbers and decide which is bigger or smaller. Use the words greater than, less than, and equal to.',
            'objectives' => "- Compare two numbers using < > =\n- Order numbers from smallest to largest\n- Use vocabulary: more, less, equal",
            'video' => 'https://www.youtube.com/watch?v=StK-2sktGkA', // Comparing Numbers
            'questions' => $this->generateComparingNumbersQuestions(),
        ]);
        
        // WEEK 2: Addition and Subtraction
        $topic2 = $this->createTopic($subjectId, 'Week 2: Addition and Subtraction', 2);
        $this->createLessonWithQuiz($topic2, [
            'title' => 'Introduction to Addition',
            'overview' => 'Understand what addition means - putting groups together. Learn the + symbol and equals = symbol.',
            'objectives' => "- Understand addition as combining groups\n- Use + and = symbols\n- Add two single-digit numbers",
            'video' => 'https://www.youtube.com/watch?v=JJgvjGpoQnw', // Addition for Kids
            'questions' => $this->generateAdditionBasicsQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic2, [
            'title' => 'Number Bonds to 10',
            'overview' => 'Learn all the ways to make 10 (like 6+4, 7+3, 8+2). Number bonds help us add quickly.',
            'objectives' => "- Know number bonds to 10 by heart\n- Find missing numbers in bonds\n- Use bonds to solve problems",
            'video' => 'https://www.youtube.com/watch?v=ch7KbtV7YhM', // Number Bonds
            'questions' => $this->generateNumberBondsQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic2, [
            'title' => 'Introduction to Subtraction',
            'overview' => 'Understand subtraction as taking away. Learn the - symbol and practice simple takeaway problems.',
            'objectives' => "- Understand subtraction as taking away\n- Use the - symbol\n- Subtract single-digit numbers",
            'video' => 'https://www.youtube.com/watch?v=BVL3292H3Vc', // Subtraction
            'questions' => $this->generateSubtractionBasicsQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic2, [
            'title' => 'Missing Number Problems',
            'overview' => 'Solve puzzles with missing numbers like 5 + ? = 8. Learn to work backwards to find the missing number.',
            'objectives' => "- Find missing numbers in addition\n- Find missing numbers in subtraction\n- Use inverse operations",
            'video' => 'https://www.youtube.com/watch?v=TliuGmJOGms', // Missing Numbers
            'questions' => $this->generateMissingNumbersQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic2, [
            'title' => 'Word Problems',
            'overview' => 'Apply addition and subtraction to real-life situations. Read problems, decide whether to add or subtract, and find the answer.',
            'objectives' => "- Read and understand word problems\n- Choose addition or subtraction\n- Solve real-life problems",
            'video' => 'https://www.youtube.com/watch?v=IeIpVJmYQUs', // Word Problems
            'questions' => $this->generateWordProblemsQuestions(),
        ]);
    }
    
    /**
     * ENGLISH YEAR 1
     */
    private function seedEnglishYear1($subjectId)
    {
        $this->command->info('  📚 English Year 1...');
        
        // WEEK 1: Phonics
        $topic1 = $this->createTopic($subjectId, 'Week 1: Phonics and Letter Sounds', 1);
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Letter Sounds A-H',
            'overview' => 'Learn the sounds that letters make. Practice saying letter sounds clearly and matching them to letters.',
            'objectives' => "- Say sounds for letters a-h\n- Match sounds to letters\n- Hear sounds in words",
            'video' => 'https://www.youtube.com/watch?v=BELlZKpi1Zs', // Letter Sounds
            'questions' => $this->generateLetterSoundsAHQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Letter Sounds I-P',
            'overview' => 'Continue learning letter sounds. Practice more letters and their sounds.',
            'objectives' => "- Say sounds for letters i-p\n- Match sounds to letters\n- Identify initial sounds",
            'video' => 'https://www.youtube.com/watch?v=36IBDpTRVNE', // More Sounds
            'questions' => $this->generateLetterSoundsIPQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Letter Sounds Q-Z',
            'overview' => 'Complete the alphabet sounds. Learn the last group of letter sounds.',
            'objectives' => "- Say sounds for letters q-z\n- Know all letter sounds\n- Segment words into sounds",
            'video' => 'https://www.youtube.com/watch?v=hq3yfQnllfQ', // Q-Z
            'questions' => $this->generateLetterSoundsQZQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Blending Sounds',
            'overview' => 'Put sounds together to read words. Learn to blend c-a-t makes cat.',
            'objectives' => "- Blend sounds to read CVC words\n- Read simple three-letter words\n- Practice oral blending",
            'video' => 'https://www.youtube.com/watch?v=dEzfpod5w_Q', // Blending
            'questions' => $this->generateBlendingQuestions(),
        ]);
        
        $this->createLessonWithQuiz($topic1, [
            'title' => 'Segmenting Words',
            'overview' => 'Break words into sounds for spelling. Learn that dog has three sounds: d-o-g.',
            'objectives' => "- Segment words into sounds\n- Count sounds in words\n- Use segmenting to spell",
            'video' => 'https://www.youtube.com/watch?v=TkXcabDUg7Q', // Segmenting
            'questions' => $this->generateSegmentingQuestions(),
        ]);
    }
    
    private function createTopic($subjectId, $title, $orderIndex)
    {
        DB::table('external_topics')->insert([
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => "Complete all lessons in this topic!",
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
     * Create lesson with embedded 10-question quiz
     */
    private function createLessonWithQuiz($topic, $data)
    {
        $quizData = [
            'questions' => $data['questions'],
            'pass_percentage' => 70,
            'points_per_question' => 2,
            'total_points' => 20,
            'time_limit_minutes' => 15,
        ];
        
        DB::table('external_lessons')->insert([
            'topic_id' => $topic->id,
            'title' => $data['title'],
            'description' => $data['overview'] . "\n\n**Learning Objectives:**\n" . $data['objectives'],
            'video_url' => $data['video'],
            'quiz_data' => json_encode($quizData),
            'duration_minutes' => 15,
            'order_index' => DB::table('external_lessons')->where('topic_id', $topic->id)->count() + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    /**
     * ==============================================
     * QUESTION GENERATORS - 10 QUESTIONS PER LESSON
     * ==============================================
     */
    
    private function generateCountingTo20Questions()
    {
        return [
            ['question' => 'What number comes after 5?', 'options' => ['4', '6', '7', '5'], 'correct_answer' => '6', 'explanation' => 'When counting, 6 comes right after 5.'],
            ['question' => 'What number comes before 10?', 'options' => ['11', '8', '9', '10'], 'correct_answer' => '9', 'explanation' => '9 comes just before 10 when counting.'],
            ['question' => 'How many fingers do you have on both hands?', 'options' => ['5', '10', '15', '20'], 'correct_answer' => '10', 'explanation' => 'You have 5 fingers on each hand, so 5 + 5 = 10 fingers total.'],
            ['question' => 'If you count to 20, what is the last number you say?', 'options' => ['19', '20', '21', '18'], 'correct_answer' => '20', 'explanation' => '20 is the last number when counting to 20.'],
            ['question' => 'What number comes between 7 and 9?', 'options' => ['6', '8', '10', '7'], 'correct_answer' => '8', 'explanation' => 'The number 8 sits between 7 and 9.'],
            ['question' => 'Which is the smallest number: 2, 5, 1, 3?', 'options' => ['2', '5', '1', '3'], 'correct_answer' => '1', 'explanation' => '1 is the smallest number in this group.'],
            ['question' => 'Which is the biggest number: 12, 15, 10, 14?', 'options' => ['12', '15', '10', '14'], 'correct_answer' => '15', 'explanation' => '15 is larger than 12, 10, and 14.'],
            ['question' => 'What number comes after 19?', 'options' => ['18', '20', '21', '19'], 'correct_answer' => '20', 'explanation' => '20 comes right after 19.'],
            ['question' => 'If you have 3 apples and count them (1, 2, 3), how many apples do you have?', 'options' => ['2', '3', '4', '1'], 'correct_answer' => '3', 'explanation' => 'You counted to 3, so you have 3 apples.'],
            ['question' => 'What is the number that looks like this: 13?', 'options' => ['Thirty', 'Thirteen', 'Three', 'Thirt'], 'correct_answer' => 'Thirteen', 'explanation' => '13 is written as thirteen.'],
        ];
    }
    
    private function generateOneMoreOneLessQuestions()
    {
        return [
            ['question' => 'What is one more than 5?', 'options' => ['4', '6', '5', '7'], 'correct_answer' => '6', 'explanation' => 'One more than 5 is 6.'],
            ['question' => 'What is one less than 10?', 'options' => ['11', '9', '10', '8'], 'correct_answer' => '9', 'explanation' => 'One less than 10 is 9.'],
            ['question' => 'If you have 7 sweets and get one more, how many do you have?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '8', 'explanation' => '7 + 1 = 8 sweets.'],
            ['question' => 'If you have 12 pencils and lose one, how many are left?', 'options' => ['13', '12', '11', '10'], 'correct_answer' => '11', 'explanation' => '12 - 1 = 11 pencils.'],
            ['question' => 'What is one more than 14?', 'options' => ['13', '14', '15', '16'], 'correct_answer' => '15', 'explanation' => 'One more than 14 is 15.'],
            ['question' => 'What is one less than 8?', 'options' => ['9', '8', '7', '6'], 'correct_answer' => '7', 'explanation' => 'One less than 8 is 7.'],
            ['question' => 'If I count "9, 10, __", what number comes next?', 'options' => ['9', '10', '11', '12'], 'correct_answer' => '11', 'explanation' => '11 is one more than 10.'],
            ['question' => 'What is one more than 19?', 'options' => ['18', '19', '20', '21'], 'correct_answer' => '20', 'explanation' => 'One more than 19 is 20.'],
            ['question' => 'What is one less than 6?', 'options' => ['7', '6', '5', '4'], 'correct_answer' => '5', 'explanation' => 'One less than 6 is 5.'],
            ['question' => 'If you take one away from 11, what do you get?', 'options' => ['12', '11', '10', '9'], 'correct_answer' => '10', 'explanation' => '11 - 1 = 10.'],
        ];
    }
    
    private function generateReadingWritingNumbersQuestions()
    {
        return [
            ['question' => 'How do you write the number "five"?', 'options' => ['3', '5', '7', '6'], 'correct_answer' => '5', 'explanation' => 'Five is written as the numeral 5.'],
            ['question' => 'What number word matches 8?', 'options' => ['Six', 'Seven', 'Eight', 'Nine'], 'correct_answer' => 'Eight', 'explanation' => '8 is written as eight.'],
            ['question' => 'How do you write "ten"?', 'options' => ['1', '10', '01', '100'], 'correct_answer' => '10', 'explanation' => 'Ten is written as 10.'],
            ['question' => 'What does 3 look like as a word?', 'options' => ['Tree', 'Three', 'Free', 'Thee'], 'correct_answer' => 'Three', 'explanation' => '3 is written as three.'],
            ['question' => 'Which numeral matches "twelve"?', 'options' => ['2', '12', '20', '21'], 'correct_answer' => '12', 'explanation' => 'Twelve is written as 12.'],
            ['question' => 'How do you write "seven"?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '7', 'explanation' => 'Seven is written as 7.'],
            ['question' => 'What number word is this: 15?', 'options' => ['Five', 'Fifty', 'Fifteen', 'Fiveteen'], 'correct_answer' => 'Fifteen', 'explanation' => '15 is written as fifteen.'],
            ['question' => 'How do you write "nine"?', 'options' => ['6', '9', '10', '8'], 'correct_answer' => '9', 'explanation' => 'Nine is written as 9.'],
            ['question' => 'What does 20 look like as a word?', 'options' => ['Two', 'Twelve', 'Twenty', 'Twonty'], 'correct_answer' => 'Twenty', 'explanation' => '20 is written as twenty.'],
            ['question' => 'Which numeral matches "four"?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '4', 'explanation' => 'Four is written as 4.'],
        ];
    }
    
    private function generateTensAndOnesQuestions()
    {
        return [
            ['question' => 'How many tens are in 14?', 'options' => ['0', '1', '2', '4'], 'correct_answer' => '1', 'explanation' => '14 has 1 ten and 4 ones.'],
            ['question' => 'How many ones are in 14?', 'options' => ['1', '4', '14', '0'], 'correct_answer' => '4', 'explanation' => '14 has 1 ten and 4 ones.'],
            ['question' => 'What number has 1 ten and 7 ones?', 'options' => ['7', '10', '17', '71'], 'correct_answer' => '17', 'explanation' => '1 ten (10) + 7 ones = 17.'],
            ['question' => 'How many ones are in 20?', 'options' => ['0', '2', '10', '20'], 'correct_answer' => '0', 'explanation' => '20 has 2 tens and 0 ones.'],
            ['question' => 'What number is made of 1 ten and 5 ones?', 'options' => ['5', '10', '15', '51'], 'correct_answer' => '15', 'explanation' => '1 ten (10) + 5 ones = 15.'],
            ['question' => 'How many tens are in 19?', 'options' => ['0', '1', '9', '19'], 'correct_answer' => '1', 'explanation' => '19 has 1 ten and 9 ones.'],
            ['question' => 'If you have 10 ones, how many tens is that?', 'options' => ['0', '1', '10', '100'], 'correct_answer' => '1', 'explanation' => '10 ones = 1 ten.'],
            ['question' => 'What number has 0 tens and 8 ones?', 'options' => ['0', '8', '10', '80'], 'correct_answer' => '8', 'explanation' => '0 tens + 8 ones = 8.'],
            ['question' => 'How many tens are in 20?', 'options' => ['0', '1', '2', '10'], 'correct_answer' => '2', 'explanation' => '20 has 2 tens and 0 ones.'],
            ['question' => 'What number is 1 ten and 2 ones?', 'options' => ['2', '10', '12', '21'], 'correct_answer' => '12', 'explanation' => '1 ten (10) + 2 ones = 12.'],
        ];
    }
    
    private function generateComparingNumbersQuestions()
    {
        return [
            ['question' => 'Which number is bigger: 7 or 5?', 'options' => ['5', '7', 'Same', 'Neither'], 'correct_answer' => '7', 'explanation' => '7 is greater than 5.'],
            ['question' => 'Which number is smaller: 12 or 15?', 'options' => ['12', '15', 'Same', 'Neither'], 'correct_answer' => '12', 'explanation' => '12 is less than 15.'],
            ['question' => 'Put these in order from smallest to biggest: 3, 1, 2', 'options' => ['1, 2, 3', '3, 2, 1', '2, 1, 3', '1, 3, 2'], 'correct_answer' => '1, 2, 3', 'explanation' => 'The correct order is 1, then 2, then 3.'],
            ['question' => 'Is 10 more than 8?', 'options' => ['Yes', 'No', 'Same', 'Maybe'], 'correct_answer' => 'Yes', 'explanation' => '10 is greater than 8.'],
            ['question' => 'Is 4 less than 6?', 'options' => ['Yes', 'No', 'Same', 'Maybe'], 'correct_answer' => 'Yes', 'explanation' => '4 is smaller than 6.'],
            ['question' => 'Which number is the biggest: 9, 11, 7?', 'options' => ['9', '11', '7', 'Same'], 'correct_answer' => '11', 'explanation' => '11 is the largest of these three numbers.'],
            ['question' => 'Which number is the smallest: 14, 12, 16?', 'options' => ['14', '12', '16', 'Same'], 'correct_answer' => '12', 'explanation' => '12 is the smallest of these three numbers.'],
            ['question' => 'Is 5 equal to 5?', 'options' => ['Yes', 'No', 'Greater', 'Less'], 'correct_answer' => 'Yes', 'explanation' => '5 is equal to 5 - they are the same!'],
            ['question' => 'Which is greater: 20 or 19?', 'options' => ['19', '20', 'Same', 'Neither'], 'correct_answer' => '20', 'explanation' => '20 is one more than 19, so it is greater.'],
            ['question' => 'Put these in order from biggest to smallest: 8, 10, 6', 'options' => ['10, 8, 6', '6, 8, 10', '8, 6, 10', '10, 6, 8'], 'correct_answer' => '10, 8, 6', 'explanation' => 'From biggest to smallest: 10, then 8, then 6.'],
        ];
    }
    
    private function generateAdditionBasicsQuestions()
    {
        return [
            ['question' => 'What is 2 + 3?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '5', 'explanation' => '2 + 3 = 5. Count: 1, 2, then 3, 4, 5.'],
            ['question' => 'What is 4 + 2?', 'options' => ['5', '6', '7', '8'], 'correct_answer' => '6', 'explanation' => '4 + 2 = 6.'],
            ['question' => 'If you have 3 apples and get 4 more, how many do you have?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '7', 'explanation' => '3 + 4 = 7 apples.'],
            ['question' => 'What is 1 + 1?', 'options' => ['1', '2', '3', '4'], 'correct_answer' => '2', 'explanation' => '1 + 1 = 2.'],
            ['question' => 'What is 5 + 3?', 'options' => ['7', '8', '9', '10'], 'correct_answer' => '8', 'explanation' => '5 + 3 = 8.'],
            ['question' => 'What is 6 + 1?', 'options' => ['5', '6', '7', '8'], 'correct_answer' => '7', 'explanation' => '6 + 1 = 7.'],
            ['question' => 'What does + mean?', 'options' => ['Take away', 'Add together', 'Equal', 'More than'], 'correct_answer' => 'Add together', 'explanation' => 'The + symbol means add or put together.'],
            ['question' => 'What is 0 + 5?', 'options' => ['0', '5', '6', '10'], 'correct_answer' => '5', 'explanation' => '0 + 5 = 5. Adding zero doesn\'t change the number.'],
            ['question' => 'What is 3 + 3?', 'options' => ['5', '6', '7', '8'], 'correct_answer' => '6', 'explanation' => '3 + 3 = 6. This is a double!'],
            ['question' => 'If you add 2 + 5, what do you get?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '7', 'explanation' => '2 + 5 = 7.'],
        ];
    }
    
    private function generateNumberBondsQuestions()
    {
        return [
            ['question' => 'What is 6 + 4?', 'options' => ['9', '10', '11', '12'], 'correct_answer' => '10', 'explanation' => '6 + 4 = 10. This is a number bond to 10!'],
            ['question' => 'What is 7 + 3?', 'options' => ['9', '10', '11', '12'], 'correct_answer' => '10', 'explanation' => '7 + 3 = 10.'],
            ['question' => 'What number goes with 2 to make 10?', 'options' => ['7', '8', '9', '10'], 'correct_answer' => '8', 'explanation' => '2 + 8 = 10.'],
            ['question' => 'What is 5 + 5?', 'options' => ['9', '10', '11', '15'], 'correct_answer' => '10', 'explanation' => '5 + 5 = 10. This is a double!'],
            ['question' => 'What number goes with 9 to make 10?', 'options' => ['0', '1', '2', '3'], 'correct_answer' => '1', 'explanation' => '9 + 1 = 10.'],
            ['question' => 'What is 8 + 2?', 'options' => ['9', '10', '11', '12'], 'correct_answer' => '10', 'explanation' => '8 + 2 = 10.'],
            ['question' => 'If you have 4 sweets, how many more do you need to have 10?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '6', 'explanation' => '4 + 6 = 10, so you need 6 more.'],
            ['question' => 'What is 10 + 0?', 'options' => ['0', '1', '10', '100'], 'correct_answer' => '10', 'explanation' => '10 + 0 = 10. Adding zero doesn\'t change the number.'],
            ['question' => 'What number goes with 3 to make 10?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '7', 'explanation' => '3 + 7 = 10.'],
            ['question' => 'What is 1 + 9?', 'options' => ['8', '9', '10', '11'], 'correct_answer' => '10', 'explanation' => '1 + 9 = 10.'],
        ];
    }
    
    private function generateSubtractionBasicsQuestions()
    {
        return [
            ['question' => 'What is 5 - 2?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '3', 'explanation' => '5 - 2 = 3. Take 2 away from 5.'],
            ['question' => 'What is 7 - 3?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '4', 'explanation' => '7 - 3 = 4.'],
            ['question' => 'If you have 8 pencils and lose 2, how many are left?', 'options' => ['5', '6', '7', '8'], 'correct_answer' => '6', 'explanation' => '8 - 2 = 6 pencils left.'],
            ['question' => 'What is 10 - 5?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '5', 'explanation' => '10 - 5 = 5.'],
            ['question' => 'What does - mean?', 'options' => ['Add', 'Take away', 'Equal', 'More'], 'correct_answer' => 'Take away', 'explanation' => 'The - symbol means subtract or take away.'],
            ['question' => 'What is 6 - 1?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '5', 'explanation' => '6 - 1 = 5.'],
            ['question' => 'What is 9 - 4?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '5', 'explanation' => '9 - 4 = 5.'],
            ['question' => 'What is 3 - 3?', 'options' => ['0', '1', '3', '6'], 'correct_answer' => '0', 'explanation' => '3 - 3 = 0. When you take away everything, you have zero left.'],
            ['question' => 'What is 10 - 1?', 'options' => ['8', '9', '10', '11'], 'correct_answer' => '9', 'explanation' => '10 - 1 = 9.'],
            ['question' => 'If you have 4 sweets and eat 2, how many are left?', 'options' => ['1', '2', '3', '4'], 'correct_answer' => '2', 'explanation' => '4 - 2 = 2 sweets left.'],
        ];
    }
    
    private function generateMissingNumbersQuestions()
    {
        return [
            ['question' => '5 + ? = 8. What number is missing?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '3', 'explanation' => '5 + 3 = 8, so the missing number is 3.'],
            ['question' => '? + 4 = 10. What number is missing?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '6', 'explanation' => '6 + 4 = 10, so the missing number is 6.'],
            ['question' => '9 - ? = 5. What number is missing?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '4', 'explanation' => '9 - 4 = 5, so the missing number is 4.'],
            ['question' => '? - 3 = 2. What number is missing?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '5', 'explanation' => '5 - 3 = 2, so the missing number is 5.'],
            ['question' => '7 + ? = 10. What number is missing?', 'options' => ['1', '2', '3', '4'], 'correct_answer' => '3', 'explanation' => '7 + 3 = 10, so the missing number is 3.'],
            ['question' => '10 - ? = 7. What number is missing?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '3', 'explanation' => '10 - 3 = 7, so the missing number is 3.'],
            ['question' => '? + 2 = 6. What number is missing?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '4', 'explanation' => '4 + 2 = 6, so the missing number is 4.'],
            ['question' => '8 - ? = 3. What number is missing?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '5', 'explanation' => '8 - 5 = 3, so the missing number is 5.'],
            ['question' => '? + 5 = 9. What number is missing?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '4', 'explanation' => '4 + 5 = 9, so the missing number is 4.'],
            ['question' => '6 - ? = 4. What number is missing?', 'options' => ['1', '2', '3', '4'], 'correct_answer' => '2', 'explanation' => '6 - 2 = 4, so the missing number is 2.'],
        ];
    }
    
    private function generateWordProblemsQuestions()
    {
        return [
            ['question' => 'Tom has 3 apples. His friend gives him 2 more. How many does he have now?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '5', 'explanation' => '3 + 2 = 5 apples.'],
            ['question' => 'There are 7 birds on a tree. 3 fly away. How many are left?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '4', 'explanation' => '7 - 3 = 4 birds left.'],
            ['question' => 'Sarah has 2 toy cars. She gets 4 more for her birthday. How many does she have?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '6', 'explanation' => '2 + 4 = 6 toy cars.'],
            ['question' => 'There are 10 cookies. You eat 3. How many are left?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '7', 'explanation' => '10 - 3 = 7 cookies left.'],
            ['question' => 'A farmer has 5 chickens. He buys 5 more. How many does he have?', 'options' => ['8', '9', '10', '11'], 'correct_answer' => '10', 'explanation' => '5 + 5 = 10 chickens.'],
            ['question' => 'There are 8 flowers. 2 die. How many are left?', 'options' => ['5', '6', '7', '8'], 'correct_answer' => '6', 'explanation' => '8 - 2 = 6 flowers left.'],
            ['question' => 'Jack has 4 pencils. His teacher gives him 3 more. How many does he have?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '7', 'explanation' => '4 + 3 = 7 pencils.'],
            ['question' => 'There are 9 children playing. 4 go home. How many are still playing?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '5', 'explanation' => '9 - 4 = 5 children still playing.'],
            ['question' => 'A shop has 6 red balloons and 3 blue balloons. How many balloons in total?', 'options' => ['7', '8', '9', '10'], 'correct_answer' => '9', 'explanation' => '6 + 3 = 9 balloons total.'],
            ['question' => 'There were 10 sweets in a jar. Someone ate 5. How many are left?', 'options' => ['4', '5', '6', '7'], 'correct_answer' => '5', 'explanation' => '10 - 5 = 5 sweets left.'],
        ];
    }
    
    // ENGLISH QUESTIONS
    private function generateLetterSoundsAHQuestions()
    {
        return [
            ['question' => 'What sound does the letter "a" make?', 'options' => ['/b/', '/a/', '/c/', '/d/'], 'correct_answer' => '/a/', 'explanation' => 'The letter a makes the /a/ sound as in apple.'],
            ['question' => 'What sound does the letter "b" make?', 'options' => ['/a/', '/b/', '/c/', '/d/'], 'correct_answer' => '/b/', 'explanation' => 'The letter b makes the /b/ sound as in bat.'],
            ['question' => 'Which letter makes the /c/ sound?', 'options' => ['a', 'b', 'c', 'd'], 'correct_answer' => 'c', 'explanation' => 'The letter c makes the /c/ sound as in cat.'],
            ['question' => 'What sound does "d" make?', 'options' => ['/a/', '/b/', '/c/', '/d/'], 'correct_answer' => '/d/', 'explanation' => 'The letter d makes the /d/ sound as in dog.'],
            ['question' => 'Which letter makes the /e/ sound as in egg?', 'options' => ['a', 'b', 'e', 'd'], 'correct_answer' => 'e', 'explanation' => 'The letter e makes the /e/ sound as in egg.'],
            ['question' => 'What sound does "f" make?', 'options' => ['/e/', '/f/', '/g/', '/h/'], 'correct_answer' => '/f/', 'explanation' => 'The letter f makes the /f/ sound as in fish.'],
            ['question' => 'Which letter makes the /g/ sound as in go?', 'options' => ['e', 'f', 'g', 'h'], 'correct_answer' => 'g', 'explanation' => 'The letter g makes the /g/ sound as in go.'],
            ['question' => 'What sound does "h" make?', 'options' => ['/f/', '/g/', '/h/', '/i/'], 'correct_answer' => '/h/', 'explanation' => 'The letter h makes the /h/ sound as in hat.'],
            ['question' => 'What is the first sound in "apple"?', 'options' => ['/a/', '/p/', '/l/', '/e/'], 'correct_answer' => '/a/', 'explanation' => 'Apple starts with the /a/ sound.'],
            ['question' => 'What is the first sound in "dog"?', 'options' => ['/d/', '/o/', '/g/', '/a/'], 'correct_answer' => '/d/', 'explanation' => 'Dog starts with the /d/ sound.'],
        ];
    }
    
    private function generateLetterSoundsIPQuestions()
    {
        return [
            ['question' => 'What sound does the letter "i" make?', 'options' => ['/h/', '/i/', '/j/', '/k/'], 'correct_answer' => '/i/', 'explanation' => 'The letter i makes the /i/ sound as in insect.'],
            ['question' => 'What sound does "j" make?', 'options' => ['/i/', '/j/', '/k/', '/l/'], 'correct_answer' => '/j/', 'explanation' => 'The letter j makes the /j/ sound as in jump.'],
            ['question' => 'Which letter makes the /k/ sound?', 'options' => ['i', 'j', 'k', 'l'], 'correct_answer' => 'k', 'explanation' => 'The letter k makes the /k/ sound as in kite.'],
            ['question' => 'What sound does "l" make?', 'options' => ['/j/', '/k/', '/l/', '/m/'], 'correct_answer' => '/l/', 'explanation' => 'The letter l makes the /l/ sound as in lion.'],
            ['question' => 'Which letter makes the /m/ sound?', 'options' => ['k', 'l', 'm', 'n'], 'correct_answer' => 'm', 'explanation' => 'The letter m makes the /m/ sound as in monkey.'],
            ['question' => 'What sound does "n" make?', 'options' => ['/l/', '/m/', '/n/', '/o/'], 'correct_answer' => '/n/', 'explanation' => 'The letter n makes the /n/ sound as in net.'],
            ['question' => 'Which letter makes the /o/ sound as in orange?', 'options' => ['m', 'n', 'o', 'p'], 'correct_answer' => 'o', 'explanation' => 'The letter o makes the /o/ sound as in orange.'],
            ['question' => 'What sound does "p" make?', 'options' => ['/n/', '/o/', '/p/', '/q/'], 'correct_answer' => '/p/', 'explanation' => 'The letter p makes the /p/ sound as in pig.'],
            ['question' => 'What is the first sound in "jump"?', 'options' => ['/j/', '/u/', '/m/', '/p/'], 'correct_answer' => '/j/', 'explanation' => 'Jump starts with the /j/ sound.'],
            ['question' => 'What is the first sound in "mop"?', 'options' => ['/m/', '/o/', '/p/', '/n/'], 'correct_answer' => '/m/', 'explanation' => 'Mop starts with the /m/ sound.'],
        ];
    }
    
    private function generateLetterSoundsQZQuestions()
    {
        return [
            ['question' => 'What sound does "q" make?', 'options' => ['/p/', '/q/', '/r/', '/s/'], 'correct_answer' => '/q/', 'explanation' => 'The letter q makes the /q/ sound as in queen.'],
            ['question' => 'What sound does "r" make?', 'options' => ['/q/', '/r/', '/s/', '/t/'], 'correct_answer' => '/r/', 'explanation' => 'The letter r makes the /r/ sound as in run.'],
            ['question' => 'Which letter makes the /s/ sound?', 'options' => ['q', 'r', 's', 't'], 'correct_answer' => 's', 'explanation' => 'The letter s makes the /s/ sound as in sun.'],
            ['question' => 'What sound does "t" make?', 'options' => ['/r/', '/s/', '/t/', '/u/'], 'correct_answer' => '/t/', 'explanation' => 'The letter t makes the /t/ sound as in top.'],
            ['question' => 'Which letter makes the /u/ sound as in umbrella?', 'options' => ['s', 't', 'u', 'v'], 'correct_answer' => 'u', 'explanation' => 'The letter u makes the /u/ sound as in umbrella.'],
            ['question' => 'What sound does "v" make?', 'options' => ['/t/', '/u/', '/v/', '/w/'], 'correct_answer' => '/v/', 'explanation' => 'The letter v makes the /v/ sound as in van.'],
            ['question' => 'Which letter makes the /w/ sound?', 'options' => ['u', 'v', 'w', 'x'], 'correct_answer' => 'w', 'explanation' => 'The letter w makes the /w/ sound as in win.'],
            ['question' => 'What sound does "x" make?', 'options' => ['/v/', '/w/', '/x/', '/y/'], 'correct_answer' => '/x/', 'explanation' => 'The letter x makes the /x/ sound as in fox.'],
            ['question' => 'Which letter makes the /y/ sound as in yes?', 'options' => ['w', 'x', 'y', 'z'], 'correct_answer' => 'y', 'explanation' => 'The letter y makes the /y/ sound as in yes.'],
            ['question' => 'What sound does "z" make?', 'options' => ['/x/', '/y/', '/z/', '/a/'], 'correct_answer' => '/z/', 'explanation' => 'The letter z makes the /z/ sound as in zoo.'],
        ];
    }
    
    private function generateBlendingQuestions()
    {
        return [
            ['question' => 'Blend these sounds: /c/ /a/ /t/. What word is it?', 'options' => ['can', 'cat', 'cap', 'car'], 'correct_answer' => 'cat', 'explanation' => '/c/ /a/ /t/ blends to make cat.'],
            ['question' => 'Blend these sounds: /d/ /o/ /g/. What word is it?', 'options' => ['dig', 'dog', 'dot', 'dug'], 'correct_answer' => 'dog', 'explanation' => '/d/ /o/ /g/ blends to make dog.'],
            ['question' => 'What word do you get from /p/ /i/ /g/?', 'options' => ['pin', 'pig', 'pit', 'peg'], 'correct_answer' => 'pig', 'explanation' => '/p/ /i/ /g/ makes pig.'],
            ['question' => 'Blend: /h/ /o/ /t/. What word?', 'options' => ['hat', 'hot', 'hit', 'hut'], 'correct_answer' => 'hot', 'explanation' => '/h/ /o/ /t/ makes hot.'],
            ['question' => 'What word is /r/ /u/ /n/?', 'options' => ['ran', 'run', 'rug', 'rut'], 'correct_answer' => 'run', 'explanation' => '/r/ /u/ /n/ makes run.'],
            ['question' => 'Blend: /b/ /e/ /d/. What word?', 'options' => ['bad', 'bed', 'bid', 'bud'], 'correct_answer' => 'bed', 'explanation' => '/b/ /e/ /d/ makes bed.'],
            ['question' => 'What word do you get from /m/ /a/ /n/?', 'options' => ['man', 'men', 'mop', 'mat'], 'correct_answer' => 'man', 'explanation' => '/m/ /a/ /n/ makes man.'],
            ['question' => 'Blend: /s/ /i/ /t/. What word?', 'options' => ['sat', 'sit', 'set', 'sun'], 'correct_answer' => 'sit', 'explanation' => '/s/ /i/ /t/ makes sit.'],
            ['question' => 'What word is /t/ /o/ /p/?', 'options' => ['tap', 'tip', 'top', 'tub'], 'correct_answer' => 'top', 'explanation' => '/t/ /o/ /p/ makes top.'],
            ['question' => 'Blend: /l/ /e/ /g/. What word?', 'options' => ['lag', 'leg', 'log', 'lug'], 'correct_answer' => 'leg', 'explanation' => '/l/ /e/ /g/ makes leg.'],
        ];
    }
    
    private function generateSegmentingQuestions()
    {
        return [
            ['question' => 'How many sounds in "cat"?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '3', 'explanation' => 'Cat has 3 sounds: /c/ /a/ /t/.'],
            ['question' => 'What are the sounds in "dog"?', 'options' => ['/d/ /g/', '/d/ /o/ /g/', '/do/ /g/', '/dog/'], 'correct_answer' => '/d/ /o/ /g/', 'explanation' => 'Dog breaks into 3 sounds: /d/ /o/ /g/.'],
            ['question' => 'How many sounds in "sun"?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '3', 'explanation' => 'Sun has 3 sounds: /s/ /u/ /n/.'],
            ['question' => 'What is the first sound in "pig"?', 'options' => ['/p/', '/i/', '/g/', '/pig/'], 'correct_answer' => '/p/', 'explanation' => 'The first sound in pig is /p/.'],
            ['question' => 'What is the last sound in "hat"?', 'options' => ['/h/', '/a/', '/t/', '/hat/'], 'correct_answer' => '/t/', 'explanation' => 'The last sound in hat is /t/.'],
            ['question' => 'How many sounds in "bed"?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '3', 'explanation' => 'Bed has 3 sounds: /b/ /e/ /d/.'],
            ['question' => 'What is the middle sound in "mop"?', 'options' => ['/m/', '/o/', '/p/', '/mop/'], 'correct_answer' => '/o/', 'explanation' => 'The middle sound in mop is /o/.'],
            ['question' => 'Break "run" into sounds.', 'options' => ['/r/ /n/', '/r/ /u/ /n/', '/ru/ /n/', '/run/'], 'correct_answer' => '/r/ /u/ /n/', 'explanation' => 'Run breaks into 3 sounds: /r/ /u/ /n/.'],
            ['question' => 'How many sounds in "kit"?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '3', 'explanation' => 'Kit has 3 sounds: /k/ /i/ /t/.'],
            ['question' => 'What is the first sound in "fox"?', 'options' => ['/f/', '/o/', '/x/', '/fox/'], 'correct_answer' => '/f/', 'explanation' => 'The first sound in fox is /f/.'],
        ];
    }
}

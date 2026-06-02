<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NigerianCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🇳🇬 Seeding Nigerian Curriculum (NERDC) - Maths & English');

        foreach ($this->getCurriculum() as $subjectData) {
            $this->seedSubject($subjectData);
        }

        $this->command->info('✅ Nigerian curriculum seeding complete!');
    }

    // =========================================================
    // MAIN SEEDER
    // =========================================================

    private function seedSubject(array $subjectData): void
    {
        $this->command->info("  Seeding: {$subjectData['name']}");

        // Find grade_level record
        $gradeLevel = DB::table('grade_levels')
            ->where('region', 'nigeria')
            ->where('display_name', $subjectData['grade_label'])
            ->first();

        // Create or update external subject
        DB::table('external_subjects')->updateOrInsert(
            [
                'name'              => $subjectData['name'],
                'curriculum_region' => 'nigeria',
            ],
            [
                'key_stage'         => $subjectData['framework_code'],
                'year_group'        => $subjectData['year_group'],
                'source'            => 'NERDC',
                'curriculum_region' => 'nigeria',
                'framework_code'    => $subjectData['framework_code'],
                'grade_level_id'    => $gradeLevel->id ?? null,
                'updated_at'        => now(),
                'created_at'        => now(),
            ]
        );

        $subject = DB::table('external_subjects')
            ->where('name', $subjectData['name'])
            ->where('curriculum_region', 'nigeria')
            ->first();

        // Seed topics and lessons
        $topicOrder = 1;
        foreach ($subjectData['topics'] as $topicData) {
            DB::table('external_topics')->updateOrInsert(
                [
                    'title'      => $topicData['title'],
                    'subject_id' => $subject->id,
                ],
                [
                    'title'       => $topicData['title'],
                    'subject_id'  => $subject->id,
                    'description' => $topicData['description'] ?? null,
                    'order_index' => $topicOrder,
                    'external_id' => 'nerdc-' . $subject->id . '-' . $topicOrder,
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );

            $topic = DB::table('external_topics')
                ->where('title', $topicData['title'])
                ->where('subject_id', $subject->id)
                ->first();

            $lessonOrder = 1;
            foreach ($topicData['lessons'] as $lessonData) {
                DB::table('external_lessons')->updateOrInsert(
                    [
                        'title'    => $lessonData['title'],
                        'topic_id' => $topic->id,
                    ],
                    [
                        'title'            => $lessonData['title'],
                        'topic_id'         => $topic->id,
                        'description'      => $lessonData['description'] ?? null,
                        'quiz_data'        => json_encode($lessonData['quiz'] ?? []),
                        'duration_minutes' => 30,
                        'order_index'      => $lessonOrder,
                        'grade_level'      => $subjectData['year_group'],
                        'week_number'      => $lessonOrder,
                        'external_id'      => substr('nerdc-lesson-' . $topic->id . '-' . $lessonOrder, 0, 100),
                        'updated_at'       => now(),
                        'created_at'       => now(),
                    ]
                );

                $lessonOrder++;
            }

            $this->command->line("    ✓ {$topicData['title']} ({$lessonOrder - 1} lessons)");
            $topicOrder++;
        }
    }

    // =========================================================
    // CURRICULUM DATA — NERDC ALIGNED
    // =========================================================

    private function getCurriculum(): array
    {
        return [
            // ── PRIMARY 1 ──────────────────────────────────────
            $this->primary1Maths(),
            $this->primary1English(),

            // ── PRIMARY 2 ──────────────────────────────────────
            $this->primary2Maths(),
            $this->primary2English(),

            // ── PRIMARY 3 ──────────────────────────────────────
            $this->primary3Maths(),
            $this->primary3English(),

            // ── PRIMARY 4 ──────────────────────────────────────
            $this->primary4Maths(),
            $this->primary4English(),

            // ── PRIMARY 5 ──────────────────────────────────────
            $this->primary5Maths(),
            $this->primary5English(),

            // ── PRIMARY 6 ──────────────────────────────────────
            $this->primary6Maths(),
            $this->primary6English(),

            // ── JSS 1 ──────────────────────────────────────────
            $this->jss1Maths(),
            $this->jss1English(),

            // ── JSS 2 ──────────────────────────────────────────
            $this->jss2Maths(),
            $this->jss2English(),

            // ── JSS 3 ──────────────────────────────────────────
            $this->jss3Maths(),
            $this->jss3English(),
        ];
    }

    // =========================================================
    // PRIMARY 1 MATHS
    // =========================================================

    private function primary1Maths(): array
    {
        return [
            'name'           => 'Mathematics Primary 1',
            'grade_label'    => 'Primary 1',
            'framework_code' => 'PRIMARY',
            'year_group'     => 1,
            'topics'         => [
                [
                    'title'       => 'Counting Numbers 1-10',
                    'description' => 'Learn to count and recognise numbers 1 to 10',
                    'lessons'     => [
                        ['title' => 'Counting objects 1 to 5', 'description' => 'Count real objects up to 5', 'quiz' => $this->quiz(['How many fingers on one hand?', 'Count: ⚽⚽⚽ — how many balls?'], [['5', '4', '3'], ['3', '2', '4']], [0, 0])],
                        ['title' => 'Counting objects 6 to 10', 'description' => 'Count objects up to 10', 'quiz' => $this->quiz(['What number comes after 7?', 'Count: ★★★★★★ — how many stars?'], [['8', '6', '9'], ['6', '7', '5']], [0, 0])],
                        ['title' => 'Writing numbers 1 to 10', 'description' => 'Trace and write numbers 1–10', 'quiz' => $this->quiz(['How do you write the number eight?', 'Which number is missing: 1, 2, __, 4?'], [['8', '6', '9'], ['3', '5', '6']], [0, 0])],
                        ['title' => 'Ordering numbers 1 to 10', 'description' => 'Arrange numbers in order', 'quiz' => $this->quiz(['Which number is biggest: 3, 7, 5?', 'Which number is smallest: 9, 2, 6?'], [['7', '5', '3'], ['2', '6', '9']], [0, 0])],
                    ],
                ],
                [
                    'title'       => 'Numbers 11-20',
                    'description' => 'Extend counting and number recognition to 20',
                    'lessons'     => [
                        ['title' => 'Counting 11 to 15', 'description' => 'Count and recognise numbers 11–15', 'quiz' => $this->quiz(['What number comes after 13?', 'Count on from 11 to 15 — how many numbers?'], [['14', '12', '15'], ['5', '4', '6']], [0, 0])],
                        ['title' => 'Counting 16 to 20', 'description' => 'Count and recognise numbers 16–20', 'quiz' => $this->quiz(['What number comes before 20?', 'Which is greater: 17 or 19?'], [['19', '18', '17'], ['19', '17', '16']], [0, 0])],
                        ['title' => 'Writing numbers 11 to 20', 'description' => 'Practise writing teen numbers', 'quiz' => $this->quiz(['How do you write sixteen?', 'What is 10 + 8?'], [['16', '17', '15'], ['18', '17', '19']], [0, 0])],
                    ],
                ],
                [
                    'title'       => 'Addition within 10',
                    'description' => 'Introduction to addition using objects',
                    'lessons'     => [
                        ['title' => 'What is addition?', 'description' => 'Understand the meaning of adding', 'quiz' => $this->quiz(['What does "add" mean?', '2 + 3 = ?'], [['Put together', 'Take away', 'Share'], ['5', '4', '6']], [0, 0])],
                        ['title' => 'Adding 1 and 2', 'description' => 'Simple addition with small numbers', 'quiz' => $this->quiz(['1 + 1 = ?', '3 + 2 = ?'], [['2', '3', '1'], ['5', '4', '6']], [0, 0])],
                        ['title' => 'Adding to make 10', 'description' => 'Number bonds to 10', 'quiz' => $this->quiz(['6 + ? = 10', '7 + 3 = ?'], [['4', '5', '3'], ['10', '9', '11']], [0, 0])],
                        ['title' => 'Addition word problems', 'description' => 'Solve simple story problems', 'quiz' => $this->quiz(['Ade has 4 mangoes. Bisi gives him 3 more. How many now?', 'There are 2 cats and 5 dogs. How many animals?'], [['7', '6', '8'], ['7', '6', '8']], [0, 0])],
                    ],
                ],
                [
                    'title'       => 'Subtraction within 10',
                    'description' => 'Introduction to taking away',
                    'lessons'     => [
                        ['title' => 'What is subtraction?', 'description' => 'Understand the meaning of taking away', 'quiz' => $this->quiz(['What does subtract mean?', '5 - 2 = ?'], [['Take away', 'Add more', 'Share'], ['3', '2', '4']], [0, 0])],
                        ['title' => 'Subtracting 1 and 2', 'description' => 'Simple subtraction with small numbers', 'quiz' => $this->quiz(['4 - 1 = ?', '6 - 2 = ?'], [['3', '2', '4'], ['4', '3', '5']], [0, 0])],
                        ['title' => 'Subtraction word problems', 'description' => 'Solve simple story problems', 'quiz' => $this->quiz(['Chidi had 8 oranges. He ate 3. How many left?', 'There were 10 birds. 4 flew away. How many remain?'], [['5', '4', '6'], ['6', '5', '7']], [0, 0])],
                    ],
                ],
                [
                    'title'       => 'Basic Shapes',
                    'description' => 'Recognise and name common 2D shapes',
                    'lessons'     => [
                        ['title' => 'Circle and Square', 'description' => 'Identify circles and squares around us', 'quiz' => $this->quiz(['How many sides does a square have?', 'A circle has ___ corners'], [['4', '3', '5'], ['0', '1', '2']], [0, 0])],
                        ['title' => 'Triangle and Rectangle', 'description' => 'Identify triangles and rectangles', 'quiz' => $this->quiz(['How many sides does a triangle have?', 'Which shape has 4 sides but two are longer?'], [['3', '4', '2'], ['Rectangle', 'Square', 'Circle']], [0, 0])],
                        ['title' => 'Shapes in everyday life', 'description' => 'Find shapes in our environment', 'quiz' => $this->quiz(['What shape is a ball?', 'What shape is a book cover?'], [['Circle', 'Square', 'Triangle'], ['Rectangle', 'Circle', 'Triangle']], [0, 0])],
                    ],
                ],
            ],
        ];
    }

    // =========================================================
    // PRIMARY 1 ENGLISH
    // =========================================================

    private function primary1English(): array
    {
        return [
            'name'           => 'English Primary 1',
            'grade_label'    => 'Primary 1',
            'framework_code' => 'PRIMARY',
            'year_group'     => 1,
            'topics'         => [
                [
                    'title'       => 'The Alphabet',
                    'description' => 'Learn the letters of the English alphabet',
                    'lessons'     => [
                        ['title' => 'Letters A to E', 'description' => 'Learn and sound out letters A, B, C, D, E', 'quiz' => $this->quiz(['What sound does "A" make?', 'Which letter comes after C?'], [['ah', 'ee', 'oo'], ['D', 'B', 'E']], [0, 0])],
                        ['title' => 'Letters F to J', 'description' => 'Learn letters F, G, H, I, J', 'quiz' => $this->quiz(['What letter does "fish" start with?', 'Which letter comes after H?'], [['F', 'G', 'P'], ['I', 'J', 'G']], [0, 0])],
                        ['title' => 'Letters K to O', 'description' => 'Learn letters K, L, M, N, O', 'quiz' => $this->quiz(['What letter does "mango" start with?', 'Which letter comes after N?'], [['M', 'K', 'L'], ['O', 'M', 'P']], [0, 0])],
                        ['title' => 'Letters P to T', 'description' => 'Learn letters P, Q, R, S, T', 'quiz' => $this->quiz(['What letter does "pencil" start with?', 'Which letter comes after R?'], [['P', 'Q', 'T'], ['S', 'T', 'Q']], [0, 0])],
                        ['title' => 'Letters U to Z', 'description' => 'Learn letters U, V, W, X, Y, Z', 'quiz' => $this->quiz(['What is the last letter of the alphabet?', 'What letter does "yam" start with?'], [['Z', 'Y', 'X'], ['Y', 'Z', 'W']], [0, 0])],
                    ],
                ],
                [
                    'title'       => 'Simple Vowels and Words',
                    'description' => 'Learn vowels and form simple words',
                    'lessons'     => [
                        ['title' => 'Vowels: A E I O U', 'description' => 'Identify and use vowels', 'quiz' => $this->quiz(['How many vowels are in the alphabet?', 'Which of these is a vowel?'], [['5', '4', '6'], ['A', 'B', 'C']], [0, 0])],
                        ['title' => 'Simple 3-letter words', 'description' => 'Read and spell cat, dog, bag, cup', 'quiz' => $this->quiz(['Spell the word for a pet that says "meow"', 'What word rhymes with "bag"?'], [['cat', 'bat', 'hat'], ['tag', 'big', 'cup']], [0, 0])],
                        ['title' => 'Word families: -at words', 'description' => 'cat, bat, hat, mat, rat', 'quiz' => $this->quiz(['Which word rhymes with "cat"?', 'Complete: b + at = ?'], [['bat', 'cup', 'dog'], ['bat', 'bit', 'but']], [0, 0])],
                    ],
                ],
                [
                    'title'       => 'Reading Simple Sentences',
                    'description' => 'Read and understand short sentences',
                    'lessons'     => [
                        ['title' => 'I can read!', 'description' => 'Read sentences like "I am a boy"', 'quiz' => $this->quiz(['Complete: "I ___ a girl"', 'What does "am" mean in the sentence "I am happy"?'], [['am', 'is', 'are'], ['I exist/feel this way', 'A place', 'An action']], [0, 0])],
                        ['title' => 'Names and greetings', 'description' => 'Read sentences with names', 'quiz' => $this->quiz(['Which sentence is correct?', 'How do you greet someone in the morning?'], [['My name is Tunde.', 'my name is tunde.', 'My name is tunde'], ['Good morning', 'Good night', 'Good afternoon']], [0, 0])],
                        ['title' => 'Describing things', 'description' => 'Use adjectives like big, small, tall', 'quiz' => $this->quiz(['The elephant is ___ (big/small)?', 'Which word describes colour?'], [['big', 'small', 'fast'], ['red', 'run', 'jump']], [0, 0])],
                    ],
                ],
                [
                    'title'       => 'Writing Practice',
                    'description' => 'Trace letters and write simple words',
                    'lessons'     => [
                        ['title' => 'Writing my name', 'description' => 'Practice writing your own name', 'quiz' => $this->quiz(['Names always start with a ___ letter', 'Which is written correctly?'], [['Capital', 'Small', 'Number'], ['Ada', 'ada', 'ADA']], [0, 0])],
                        ['title' => 'Writing simple words', 'description' => 'Write words like sun, pen, hen', 'quiz' => $this->quiz(['How many letters in "pen"?', 'Which is a word?'], [['3', '2', '4'], ['cat', 'xqz', 'bbb']], [0, 0])],
                    ],
                ],
            ],
        ];
    }

    // =========================================================
    // PRIMARY 2 MATHS
    // =========================================================

    private function primary2Maths(): array
    {
        return [
            'name' => 'Mathematics Primary 2', 'grade_label' => 'Primary 2',
            'framework_code' => 'PRIMARY', 'year_group' => 2,
            'topics' => [
                ['title' => 'Numbers 1-50', 'description' => 'Count, read and write numbers up to 50',
                 'lessons' => [
                    ['title' => 'Counting in tens to 50', 'description' => 'Count 10, 20, 30, 40, 50', 'quiz' => $this->quiz(['What comes after 30 when counting in tens?', 'How many tens in 50?'], [['40', '35', '50'], ['5', '4', '6']], [0, 0])],
                    ['title' => 'Place value: tens and ones', 'description' => 'Understand tens and units', 'quiz' => $this->quiz(['In 34, how many tens?', 'In 47, how many ones?'], [['3', '4', '2'], ['7', '4', '6']], [0, 0])],
                    ['title' => 'Comparing numbers to 50', 'description' => 'Use greater than and less than', 'quiz' => $this->quiz(['Which is greater: 32 or 23?', '45 is ___ than 54'], [['32', '23', 'equal'], ['less', 'greater', 'equal']], [0, 0])],
                ]],
                ['title' => 'Addition within 20', 'description' => 'Add two numbers with answers up to 20',
                 'lessons' => [
                    ['title' => 'Adding single digits', 'description' => '6+7, 8+5, 9+4', 'quiz' => $this->quiz(['6 + 7 = ?', '8 + 9 = ?'], [['13', '12', '14'], ['17', '16', '18']], [0, 0])],
                    ['title' => 'Adding a single digit to a teen number', 'description' => '12+6, 15+4', 'quiz' => $this->quiz(['12 + 6 = ?', '15 + 4 = ?'], [['18', '17', '19'], ['19', '18', '20']], [0, 0])],
                    ['title' => 'Addition word problems', 'description' => 'Solve real-life addition stories', 'quiz' => $this->quiz(['Kemi bought 9 apples and 8 oranges. Total?', 'Class has 11 boys and 7 girls. How many pupils?'], [['17', '16', '18'], ['18', '17', '19']], [0, 0])],
                ]],
                ['title' => 'Subtraction within 20', 'description' => 'Subtract with answers up to 20',
                 'lessons' => [
                    ['title' => 'Subtracting single digits from teen numbers', 'description' => '18-6, 15-7', 'quiz' => $this->quiz(['18 - 6 = ?', '15 - 7 = ?'], [['12', '11', '13'], ['8', '7', '9']], [0, 0])],
                    ['title' => 'Subtraction word problems', 'description' => 'Solve take-away stories', 'quiz' => $this->quiz(['Emeka had 20 sweets and shared 9. How many left?', 'A tree had 17 fruits, 8 fell. How many remain?'], [['11', '10', '12'], ['9', '8', '10']], [0, 0])],
                ]],
                ['title' => 'Multiplication — Early Concepts', 'description' => 'Introduction to repeated addition',
                 'lessons' => [
                    ['title' => 'Repeated addition', 'description' => '2+2+2 = 3 twos', 'quiz' => $this->quiz(['2+2+2 = ? x 2', '3 groups of 4 = ?'], [['3', '2', '4'], ['12', '9', '8']], [0, 0])],
                    ['title' => 'Multiplication by 2', 'description' => 'The 2 times table', 'quiz' => $this->quiz(['2 x 4 = ?', '2 x 7 = ?'], [['8', '6', '10'], ['14', '12', '16']], [0, 0])],
                    ['title' => 'Multiplication by 5', 'description' => 'The 5 times table', 'quiz' => $this->quiz(['5 x 3 = ?', '5 x 6 = ?'], [['15', '10', '20'], ['30', '25', '35']], [0, 0])],
                ]],
            ],
        ];
    }

    // =========================================================
    // PRIMARY 2 ENGLISH
    // =========================================================

    private function primary2English(): array
    {
        return [
            'name' => 'English Primary 2', 'grade_label' => 'Primary 2',
            'framework_code' => 'PRIMARY', 'year_group' => 2,
            'topics' => [
                ['title' => 'Nouns', 'description' => 'Identify and use naming words',
                 'lessons' => [
                    ['title' => 'What is a noun?', 'description' => 'Names of people, places, animals, things', 'quiz' => $this->quiz(['Which word is a noun?', 'Is "Lagos" a noun?'], [['teacher', 'run', 'happy'], ['Yes', 'No', 'Sometimes']], [0, 0])],
                    ['title' => 'Common and proper nouns', 'description' => 'Difference between "city" and "Abuja"', 'quiz' => $this->quiz(['Which is a proper noun?', 'Do proper nouns need a capital letter?'], [['Nigeria', 'country', 'city'], ['Yes', 'No', 'Sometimes']], [0, 0])],
                    ['title' => 'Singular and plural nouns', 'description' => 'One book, two books', 'quiz' => $this->quiz(['Plural of "dog" is?', 'Plural of "child" is?'], [['dogs', 'dog', 'dogges'], ['children', 'childs', 'childes']], [0, 0])],
                ]],
                ['title' => 'Verbs', 'description' => 'Identify and use action words',
                 'lessons' => [
                    ['title' => 'What is a verb?', 'description' => 'Action words: run, jump, eat', 'quiz' => $this->quiz(['Which word is a verb?', 'Identify the verb: "The boy runs fast"'], [['jump', 'boy', 'fast'], ['runs', 'boy', 'fast']], [0, 0])],
                    ['title' => 'Present tense verbs', 'description' => 'Actions happening now', 'quiz' => $this->quiz(['She ___ to school every day.', 'The baby ___ loudly.'], [['walks', 'walked', 'will walk'], ['cries', 'cried', 'will cry']], [0, 0])],
                ]],
                ['title' => 'Simple Sentences', 'description' => 'Write and punctuate simple sentences',
                 'lessons' => [
                    ['title' => 'Parts of a sentence', 'description' => 'Subject and predicate', 'quiz' => $this->quiz(['Every sentence needs a ___ and a verb', 'Which is a complete sentence?'], [['subject', 'comma', 'letter'], ['The cat sleeps.', 'The cat', 'Sleeps fast']], [0, 0])],
                    ['title' => 'Capital letters and full stops', 'description' => 'Correct sentence punctuation', 'quiz' => $this->quiz(['Sentences start with a ___', 'Sentences end with a ___'], [['Capital letter', 'small letter', 'number'], ['Full stop', 'Comma', 'Letter']], [0, 0])],
                    ['title' => 'Question sentences', 'description' => 'Asking questions with question marks', 'quiz' => $this->quiz(['Questions end with a ___', 'Which is a question?'], [['?', '.', '!'], ['What is your name?', 'My name is Tolu.', 'Run fast!']], [0, 0])],
                ]],
            ],
        ];
    }

    // =========================================================
    // PRIMARY 3-6 & JSS 1-3 (Condensed but complete)
    // =========================================================

    private function primary3Maths(): array
    {
        return ['name' => 'Mathematics Primary 3', 'grade_label' => 'Primary 3', 'framework_code' => 'PRIMARY', 'year_group' => 3,
            'topics' => [
                ['title' => 'Numbers up to 999', 'description' => 'Hundreds, tens and ones', 'lessons' => [
                    ['title' => 'Place value to 999', 'description' => 'Hundreds, tens and ones', 'quiz' => $this->quiz(['In 345, the digit 3 means?', 'What is the value of 5 in 256?'], [['300', '3', '30'], ['5', '50', '500']], [0, 0])],
                    ['title' => 'Comparing 3-digit numbers', 'description' => 'Which is larger or smaller?', 'quiz' => $this->quiz(['Which is greater: 456 or 465?', '329 is ___ than 392'], [['465', '456', 'equal'], ['less', 'greater', 'equal']], [0, 0])],
                ]],
                ['title' => 'Multiplication Tables', 'description' => 'Times tables 2 to 10', 'lessons' => [
                    ['title' => 'Times tables 2 and 3', 'description' => 'Memorise 2x and 3x tables', 'quiz' => $this->quiz(['3 x 7 = ?', '2 x 9 = ?'], [['21', '18', '24'], ['18', '16', '20']], [0, 0])],
                    ['title' => 'Times tables 4 and 5', 'description' => 'Memorise 4x and 5x tables', 'quiz' => $this->quiz(['4 x 6 = ?', '5 x 8 = ?'], [['24', '20', '28'], ['40', '35', '45']], [0, 0])],
                    ['title' => 'Times tables 6 to 10', 'description' => 'Memorise 6x to 10x tables', 'quiz' => $this->quiz(['6 x 7 = ?', '9 x 8 = ?'], [['42', '36', '48'], ['72', '63', '81']], [0, 0])],
                ]],
                ['title' => 'Division Basics', 'description' => 'Introduction to sharing equally', 'lessons' => [
                    ['title' => 'What is division?', 'description' => 'Sharing into equal groups', 'quiz' => $this->quiz(['12 ÷ 3 = ?', '20 ÷ 4 = ?'], [['4', '3', '5'], ['5', '4', '6']], [0, 0])],
                    ['title' => 'Division word problems', 'description' => 'Share sweets equally', 'quiz' => $this->quiz(['24 sweets shared among 6 children. Each gets?', '30 books in 5 rows. Books per row?'], [['4', '3', '5'], ['6', '5', '7']], [0, 0])],
                ]],
                ['title' => 'Fractions', 'description' => 'Halves, quarters and thirds', 'lessons' => [
                    ['title' => 'Half and quarter', 'description' => '½ and ¼ of shapes and numbers', 'quiz' => $this->quiz(['Half of 20 is?', 'Quarter of 16 is?'], [['10', '8', '12'], ['4', '8', '2']], [0, 0])],
                    ['title' => 'Finding fractions of numbers', 'description' => 'One third of 12', 'quiz' => $this->quiz(['⅓ of 12 = ?', '¼ of 20 = ?'], [['4', '3', '6'], ['5', '4', '6']], [0, 0])],
                ]],
            ]];
    }

    private function primary3English(): array
    {
        return ['name' => 'English Primary 3', 'grade_label' => 'Primary 3', 'framework_code' => 'PRIMARY', 'year_group' => 3,
            'topics' => [
                ['title' => 'Adjectives', 'description' => 'Describing words', 'lessons' => [
                    ['title' => 'What are adjectives?', 'description' => 'Words that describe nouns', 'quiz' => $this->quiz(['Which word is an adjective?', 'Adjectives describe ___'], [['tall', 'run', 'Lagos'], ['nouns', 'verbs', 'sentences']], [0, 0])],
                    ['title' => 'Adjectives of size and colour', 'description' => 'big, small, red, blue', 'quiz' => $this->quiz(['The ___ elephant walked slowly.', 'She wore a ___ dress.'], [['large', 'run', 'Lagos'], ['beautiful', 'quickly', 'and']], [0, 0])],
                ]],
                ['title' => 'Reading Comprehension', 'description' => 'Understand short passages', 'lessons' => [
                    ['title' => 'Reading for meaning', 'description' => 'Find the main idea', 'quiz' => $this->quiz(['The main idea is what a passage is ___ about', 'A comprehension question asks you to ___'], [['mainly', 'slightly', 'never'], ['understand the text', 'copy the text', 'ignore the text']], [0, 0])],
                    ['title' => 'Answering questions from a passage', 'description' => 'Find answers in the text', 'quiz' => $this->quiz(['Where should you look for answers?', 'Re-reading helps you to ___'], [['In the passage', 'In your head', 'In a dictionary'], ['understand better', 'write faster', 'speak louder']], [0, 0])],
                ]],
                ['title' => 'Creative Writing', 'description' => 'Write short stories and descriptions', 'lessons' => [
                    ['title' => 'Writing a short story', 'description' => 'Beginning, middle and end', 'quiz' => $this->quiz(['A story has a beginning, ___ and end', 'The characters are the ___ in a story'], [['middle', 'comma', 'title'], ['people/animals', 'places', 'events']], [0, 0])],
                    ['title' => 'Descriptive writing', 'description' => 'Describe a market scene', 'quiz' => $this->quiz(['Good descriptions use ___ words', 'Which sentence is more descriptive?'], [['adjectives', 'numbers', 'only verbs'], ['The busy, colourful market buzzed with noise.', 'The market was there.', 'People walked.']], [0, 0])],
                ]],
            ]];
    }

    private function primary4Maths(): array
    {
        return ['name' => 'Mathematics Primary 4', 'grade_label' => 'Primary 4', 'framework_code' => 'PRIMARY', 'year_group' => 4,
            'topics' => [
                ['title' => 'Large Numbers to 10,000', 'description' => 'Thousands, hundreds, tens, ones', 'lessons' => [
                    ['title' => 'Place value to 10,000', 'description' => 'Read and write large numbers', 'quiz' => $this->quiz(['In 4,352 what is the value of 4?', 'Write six thousand and forty-five in digits'], [['4000', '400', '40'], ['6045', '6450', '6405']], [0, 0])],
                    ['title' => 'Rounding to nearest 10 and 100', 'description' => 'Approximation skills', 'quiz' => $this->quiz(['Round 347 to the nearest 100', 'Round 82 to the nearest 10'], [['300', '400', '350'], ['80', '90', '70']], [0, 0])],
                ]],
                ['title' => 'Long Multiplication', 'description' => 'Multiply 2-digit by 1-digit numbers', 'lessons' => [
                    ['title' => 'Multiplying 2-digit by 1-digit', 'description' => '23 x 4, 35 x 6', 'quiz' => $this->quiz(['23 x 4 = ?', '35 x 6 = ?'], [['92', '82', '102'], ['210', '200', '220']], [0, 0])],
                    ['title' => 'Multiplication word problems', 'description' => 'Real-life multiplication', 'quiz' => $this->quiz(['A bag holds 24 oranges. 5 bags hold?', 'Each row has 18 seats. 4 rows have?'], [['120', '110', '130'], ['72', '62', '82']], [0, 0])],
                ]],
                ['title' => 'Decimals and Money', 'description' => 'Use of Naira and Kobo', 'lessons' => [
                    ['title' => 'Introduction to decimals', 'description' => '0.5 = half, 0.25 = quarter', 'quiz' => $this->quiz(['0.5 is the same as?', '0.25 is the same as?'], [['½', '¼', '¾'], ['¼', '½', '¾']], [0, 0])],
                    ['title' => 'Money: Naira and Kobo', 'description' => 'Calculate prices and change', 'quiz' => $this->quiz(['₦50 + ₦35 = ?', 'Change from ₦100 when you spend ₦65?'], [['₦85', '₦80', '₦90'], ['₦35', '₦45', '₦25']], [0, 0])],
                ]],
            ]];
    }

    private function primary4English(): array
    {
        return ['name' => 'English Primary 4', 'grade_label' => 'Primary 4', 'framework_code' => 'PRIMARY', 'year_group' => 4,
            'topics' => [
                ['title' => 'Tenses', 'description' => 'Past, present and future tense', 'lessons' => [
                    ['title' => 'Present tense', 'description' => 'Actions happening now', 'quiz' => $this->quiz(['She ___ her homework now. (do)', 'They ___ football every day.'], [['is doing', 'did', 'will do'], ['play', 'played', 'will play']], [0, 0])],
                    ['title' => 'Past tense', 'description' => 'Actions that already happened', 'quiz' => $this->quiz(['Yesterday, he ___ to school. (go)', 'She ___ a letter last week.'], [['went', 'goes', 'will go'], ['wrote', 'writes', 'will write']], [0, 0])],
                    ['title' => 'Future tense', 'description' => 'Actions that will happen', 'quiz' => $this->quiz(['Tomorrow, I ___ my friend. (visit)', 'They ___ the match next week.'], [['will visit', 'visited', 'visit'], ['will watch', 'watched', 'watch']], [0, 0])],
                ]],
                ['title' => 'Comprehension Skills', 'description' => 'Read and analyse longer passages', 'lessons' => [
                    ['title' => 'Finding the main idea', 'description' => 'What is the passage mainly about?', 'quiz' => $this->quiz(['The main idea is usually found ___', 'Supporting details help to ___'], [['at the beginning or end', 'in the middle only', 'never stated'], ['explain the main idea', 'change the main idea', 'hide the main idea']], [0, 0])],
                    ['title' => 'Inference skills', 'description' => 'Read between the lines', 'quiz' => $this->quiz(['Inference means figuring out what is ___', 'Which clue word signals a conclusion?'], [['not directly stated', 'directly stated', 'invented'], ['therefore', 'because', 'and']], [0, 0])],
                ]],
                ['title' => 'Letter Writing', 'description' => 'Formal and informal letters', 'lessons' => [
                    ['title' => 'Parts of a letter', 'description' => 'Address, date, salutation, body, closing', 'quiz' => $this->quiz(['A letter begins with a ___', '"Yours faithfully" is a ___'], [['salutation', 'full stop', 'paragraph'], ['closing', 'greeting', 'heading']], [0, 0])],
                    ['title' => 'Informal letter writing', 'description' => 'Write a letter to a friend', 'quiz' => $this->quiz(['Informal letters are written to ___', 'An informal letter uses ___ language'], [['friends and family', 'strangers', 'the president'], ['friendly', 'formal', 'technical']], [0, 0])],
                ]],
            ]];
    }

    private function primary5Maths(): array
    {
        return ['name' => 'Mathematics Primary 5', 'grade_label' => 'Primary 5', 'framework_code' => 'PRIMARY', 'year_group' => 5,
            'topics' => [
                ['title' => 'Percentages', 'description' => 'Calculate percentages of numbers', 'lessons' => [
                    ['title' => 'What is a percentage?', 'description' => 'Per hundred — 50% = 50 out of 100', 'quiz' => $this->quiz(['50% of 80 = ?', '25% of 40 = ?'], [['40', '50', '30'], ['10', '20', '5']], [0, 0])],
                    ['title' => 'Percentage word problems', 'description' => 'Discount, profit and loss', 'quiz' => $this->quiz(['A shirt costs ₦2000. 10% discount. Final price?', '20% of 150 students passed. How many passed?'], [['₦1800', '₦1900', '₦1700'], ['30', '25', '35']], [0, 0])],
                ]],
                ['title' => 'Area and Perimeter', 'description' => 'Calculate area and perimeter of shapes', 'lessons' => [
                    ['title' => 'Perimeter of rectangles', 'description' => 'Add all sides', 'quiz' => $this->quiz(['Perimeter of a 5cm x 3cm rectangle?', 'Perimeter of a square with side 6cm?'], [['16cm', '15cm', '18cm'], ['24cm', '20cm', '28cm']], [0, 0])],
                    ['title' => 'Area of rectangles', 'description' => 'Length x Width', 'quiz' => $this->quiz(['Area of 8cm x 5cm rectangle?', 'Area of 7cm x 7cm square?'], [['40cm²', '35cm²', '45cm²'], ['49cm²', '42cm²', '56cm²']], [0, 0])],
                ]],
                ['title' => 'Data Handling', 'description' => 'Read and draw bar charts and pictograms', 'lessons' => [
                    ['title' => 'Reading bar charts', 'description' => 'Interpret data from bar charts', 'quiz' => $this->quiz(['The tallest bar shows the ___ value', 'What does the vertical axis show?'], [['highest', 'lowest', 'middle'], ['frequency/amount', 'names', 'colours']], [0, 0])],
                    ['title' => 'Drawing pictograms', 'description' => 'Represent data with symbols', 'quiz' => $this->quiz(['In a pictogram, each symbol represents ___', 'A key tells you what ___ represents'], [['a fixed amount', 'one item only', 'the title'], ['each symbol', 'each colour', 'the axis']], [0, 0])],
                ]],
            ]];
    }

    private function primary5English(): array
    {
        return ['name' => 'English Primary 5', 'grade_label' => 'Primary 5', 'framework_code' => 'PRIMARY', 'year_group' => 5,
            'topics' => [
                ['title' => 'Comprehension and Summary', 'description' => 'Read, summarise and analyse texts', 'lessons' => [
                    ['title' => 'Summarising a passage', 'description' => 'Pick out key points', 'quiz' => $this->quiz(['A summary includes ___ information', 'You should write a summary in your ___'], [['only key', 'all', 'no'], ['own words', 'the author\'s exact words', 'bullet points only']], [0, 0])],
                    ['title' => 'Vocabulary in context', 'description' => 'Guess word meaning from context', 'quiz' => $this->quiz(['Context clues help you find the ___ of unknown words', 'Synonyms are words that have ___ meaning'], [['meaning', 'spelling', 'pronunciation'], ['similar', 'opposite', 'no']], [0, 0])],
                ]],
                ['title' => 'Speech and Punctuation', 'description' => 'Direct and indirect speech', 'lessons' => [
                    ['title' => 'Direct speech', 'description' => 'Using quotation marks correctly', 'quiz' => $this->quiz(['Direct speech uses ___', 'She said, "I am hungry." — where is the direct speech?'], [['"quotation marks"', 'brackets', 'commas only'], ['"I am hungry."', 'She said', 'the full sentence']], [0, 0])],
                    ['title' => 'Reported (indirect) speech', 'description' => 'He said that he was hungry', 'quiz' => $this->quiz(['In reported speech, "I" often changes to ___', 'Direct: "I am tired." Reported: She said she ___ tired.'], [['he/she', 'we', 'you'], ['was', 'is', 'will be']], [0, 0])],
                ]],
                ['title' => 'Essay Writing', 'description' => 'Write structured essays', 'lessons' => [
                    ['title' => 'Structure of an essay', 'description' => 'Introduction, body, conclusion', 'quiz' => $this->quiz(['An essay has ___ main parts', 'The introduction should ___'], [['3', '2', '4'], ['introduce the topic', 'give the conclusion', 'list all facts']], [0, 0])],
                    ['title' => 'Argumentative essay', 'description' => 'Give your opinion with reasons', 'quiz' => $this->quiz(['An argumentative essay presents your ___', 'Good arguments use ___ to support points'], [['opinion with reasons', 'only facts', 'a story'], ['evidence', 'emotions', 'guessing']], [0, 0])],
                ]],
            ]];
    }

    private function primary6Maths(): array
    {
        return ['name' => 'Mathematics Primary 6', 'grade_label' => 'Primary 6', 'framework_code' => 'PRIMARY', 'year_group' => 6,
            'topics' => [
                ['title' => 'Ratio and Proportion', 'description' => 'Compare quantities using ratios', 'lessons' => [
                    ['title' => 'Introduction to ratio', 'description' => '2:3 means 2 parts to 3 parts', 'quiz' => $this->quiz(['In ratio 3:5, total parts = ?', 'Simplify ratio 4:8'], [['8', '15', '6'], ['1:2', '2:4', '4:8']], [0, 0])],
                    ['title' => 'Solving ratio problems', 'description' => 'Divide quantities in a given ratio', 'quiz' => $this->quiz(['Share ₦120 in ratio 2:3. Smaller share = ?', 'Ratio of boys to girls is 3:2. 25 pupils total. How many girls?'], [['₦48', '₦60', '₦72'], ['10', '15', '8']], [0, 0])],
                ]],
                ['title' => 'Algebra Basics', 'description' => 'Introduction to simple equations', 'lessons' => [
                    ['title' => 'Using letters for unknowns', 'description' => 'x + 5 = 10, find x', 'quiz' => $this->quiz(['x + 5 = 10, x = ?', 'y - 3 = 7, y = ?'], [['5', '15', '4'], ['10', '4', '7']], [0, 0])],
                    ['title' => 'Simple equations', 'description' => 'Solve 2x = 12', 'quiz' => $this->quiz(['2x = 12, x = ?', '3n = 21, n = ?'], [['6', '12', '8'], ['7', '8', '6']], [0, 0])],
                ]],
                ['title' => 'COMMON ENTRANCE Revision', 'description' => 'Prepare for secondary school entrance', 'lessons' => [
                    ['title' => 'Number and operations revision', 'description' => 'Revise all number topics', 'quiz' => $this->quiz(['LCM of 4 and 6 = ?', 'HCF of 12 and 18 = ?'], [['12', '8', '24'], ['6', '3', '9']], [0, 0])],
                    ['title' => 'Fractions, decimals, percentages revision', 'description' => 'Convert between forms', 'quiz' => $this->quiz(['0.75 as a fraction = ?', '½ as a percentage = ?'], [['¾', '⅔', '⅕'], ['50%', '25%', '75%']], [0, 0])],
                    ['title' => 'Geometry and measurement revision', 'description' => 'Revise shapes and measurement', 'quiz' => $this->quiz(['Angles in a triangle add up to ___°', 'Area of triangle = ½ × base × ___'], [['180', '360', '90'], ['height', 'width', 'length']], [0, 0])],
                ]],
            ]];
    }

    private function primary6English(): array
    {
        return ['name' => 'English Primary 6', 'grade_label' => 'Primary 6', 'framework_code' => 'PRIMARY', 'year_group' => 6,
            'topics' => [
                ['title' => 'Comprehension — Advanced', 'description' => 'Analyse and evaluate texts', 'lessons' => [
                    ['title' => 'Author\'s purpose', 'description' => 'Why did the author write this?', 'quiz' => $this->quiz(['Authors write to inform, ___ or persuade', 'A newspaper article aims to ___'], [['entertain', 'confuse', 'ignore'], ['inform', 'entertain', 'persuade only']], [0, 0])],
                    ['title' => 'Fact and opinion', 'description' => 'Tell facts from opinions', 'quiz' => $this->quiz(['A fact can be ___', '"Nigeria is the best country" is a ___'], [['proved', 'felt', 'guessed'], ['opinion', 'fact', 'neither']], [0, 0])],
                ]],
                ['title' => 'Formal Letter Writing', 'description' => 'Write formal letters correctly', 'lessons' => [
                    ['title' => 'Format of a formal letter', 'description' => 'Address, date, subject, body, closing', 'quiz' => $this->quiz(['Formal letters begin with "Dear Sir/Madam" or ___', '"Yours faithfully" is used when you ___'], [['Dear + Title + Surname', 'Hi', 'Hello friend'], ['do not know the person\'s name', 'know the person', 'write to a friend']], [0, 0])],
                    ['title' => 'Writing a formal letter', 'description' => 'Application and complaint letters', 'quiz' => $this->quiz(['A formal letter uses ___ language', 'Which is a correct formal opening?'], [['formal', 'casual', 'slang'], ['Dear Sir,', 'Hey,', 'Yo,']], [0, 0])],
                ]],
                ['title' => 'COMMON ENTRANCE English Revision', 'description' => 'Prepare for secondary school', 'lessons' => [
                    ['title' => 'Grammar revision', 'description' => 'Nouns, verbs, adjectives, adverbs', 'quiz' => $this->quiz(['An adverb modifies a ___', 'Which is an adverb?'], [['verb', 'noun', 'sentence'], ['quickly', 'quick', 'quickness']], [0, 0])],
                    ['title' => 'Comprehension practice', 'description' => 'Full passage with questions', 'quiz' => $this->quiz(['Always read the passage ___ before answering', 'Underline ___ when reading a comprehension'], [['carefully', 'quickly', 'once'], ['key words', 'all words', 'no words']], [0, 0])],
                ]],
            ]];
    }

    // =========================================================
    // JSS 1-3 MATHS & ENGLISH
    // =========================================================

    private function jss1Maths(): array
    {
        return ['name' => 'Mathematics JSS 1', 'grade_label' => 'JSS 1', 'framework_code' => 'JSS', 'year_group' => 7,
            'topics' => [
                ['title' => 'Whole Numbers and Integers', 'description' => 'Large numbers, negative numbers', 'lessons' => [
                    ['title' => 'Counting in millions', 'description' => 'Read and write up to millions', 'quiz' => $this->quiz(['Write 2,500,000 in words', '5 million + 300 thousand = ?'], [['Two million, five hundred thousand', 'Two thousand, five hundred', 'Twenty-five thousand'], ['5,300,000', '5,030,000', '5,003,000']], [0, 0])],
                    ['title' => 'Negative numbers', 'description' => 'Temperatures below zero', 'quiz' => $this->quiz(['-5 + 8 = ?', 'Which is smaller: -3 or -7?'], [['3', '-3', '13'], ['-7', '-3', 'equal']], [0, 0])],
                ]],
                ['title' => 'Basic Algebra', 'description' => 'Algebraic expressions and equations', 'lessons' => [
                    ['title' => 'Algebraic expressions', 'description' => '3x + 2, 5y - 1', 'quiz' => $this->quiz(['In 4x + 3, what is the coefficient of x?', 'If x = 2, find 3x + 5'], [['4', '3', '1'], ['11', '10', '9']], [0, 0])],
                    ['title' => 'Simple linear equations', 'description' => 'Solve for x in one step', 'quiz' => $this->quiz(['3x = 15, x = ?', 'x + 9 = 20, x = ?'], [['5', '6', '4'], ['11', '9', '12']], [0, 0])],
                    ['title' => 'Equations with two steps', 'description' => 'Solve 2x + 3 = 11', 'quiz' => $this->quiz(['2x + 3 = 11, x = ?', '3x - 4 = 14, x = ?'], [['4', '5', '3'], ['6', '5', '7']], [0, 0])],
                ]],
                ['title' => 'Geometry — Angles', 'description' => 'Types of angles and measurements', 'lessons' => [
                    ['title' => 'Types of angles', 'description' => 'Acute, obtuse, right, reflex', 'quiz' => $this->quiz(['An angle of 90° is called a ___ angle', 'An angle between 90° and 180° is ___'], [['right', 'acute', 'obtuse'], ['obtuse', 'acute', 'reflex']], [0, 0])],
                    ['title' => 'Angles on a straight line', 'description' => 'Angles that add up to 180°', 'quiz' => $this->quiz(['Angles on a straight line add up to ___°', 'If one angle is 70°, the other is ___°'], [['180', '360', '90'], ['110', '70', '120']], [0, 0])],
                ]],
                ['title' => 'Statistics', 'description' => 'Collect, organise and interpret data', 'lessons' => [
                    ['title' => 'Mean, median and mode', 'description' => 'Averages of a data set', 'quiz' => $this->quiz(['Find the mean of: 4, 6, 8, 10, 12', 'The mode is the ___ occurring value'], [['8', '6', '10'], ['most', 'least', 'middle']], [0, 0])],
                    ['title' => 'Frequency tables', 'description' => 'Tally and frequency', 'quiz' => $this->quiz(['Frequency means how many ___ a value appears', 'A tally mark of |||| means?'], [['times', 'ways', 'rows'], ['5', '4', '6']], [0, 0])],
                ]],
            ]];
    }

    private function jss1English(): array
    {
        return ['name' => 'English JSS 1', 'grade_label' => 'JSS 1', 'framework_code' => 'JSS', 'year_group' => 7,
            'topics' => [
                ['title' => 'Spoken English and Listening', 'description' => 'Pronunciation, stress and intonation', 'lessons' => [
                    ['title' => 'Word stress and syllables', 'description' => 'How to stress words correctly', 'quiz' => $this->quiz(['How many syllables in "beautiful"?', 'Which syllable is stressed in "reCORD" (verb)?'], [['3', '2', '4'], ['2nd', '1st', '3rd']], [0, 0])],
                    ['title' => 'Formal and informal speech', 'description' => 'When to use formal English', 'quiz' => $this->quiz(['You would use formal English when speaking to ___', 'Informal speech is used with ___'], [['a teacher', 'a best friend', 'a sibling'], ['friends', 'strangers', 'employers']], [0, 0])],
                ]],
                ['title' => 'Grammar — Parts of Speech', 'description' => 'All 8 parts of speech', 'lessons' => [
                    ['title' => 'Pronouns', 'description' => 'I, you, he, she, we, they', 'quiz' => $this->quiz(['Replace "Amaka" with a pronoun: Amaka is tall.', 'Which is an object pronoun?'], [['She', 'He', 'They'], ['him', 'he', 'I']], [0, 0])],
                    ['title' => 'Prepositions', 'description' => 'in, on, under, beside, between', 'quiz' => $this->quiz(['The book is ___ the table. (on/under)', 'He sat ___ his two friends.'], [['on', 'in', 'at'], ['between', 'beside', 'above']], [0, 0])],
                    ['title' => 'Conjunctions', 'description' => 'and, but, or, because, although', 'quiz' => $this->quiz(['Join: I was tired. I kept working. (but)', 'I stayed indoors ___ it was raining.'], [['but', 'and', 'or'], ['because', 'although', 'and']], [0, 0])],
                ]],
                ['title' => 'Reading and Literature', 'description' => 'Prose and poetry appreciation', 'lessons' => [
                    ['title' => 'Introduction to poetry', 'description' => 'Rhyme, rhythm and figures of speech', 'quiz' => $this->quiz(['Rhyming words sound ___ at the end', 'Simile compares using "like" or ___'], [['alike', 'different', 'opposite'], ['"as"', '"and"', '"but"']], [0, 0])],
                    ['title' => 'Reading prose fiction', 'description' => 'Character, setting, plot', 'quiz' => $this->quiz(['The setting of a story is ___ and ___', 'The plot is the sequence of ___'], [['when and where', 'who and why', 'what and how'], ['events', 'characters', 'descriptions']], [0, 0])],
                ]],
            ]];
    }

    private function jss2Maths(): array
    {
        return ['name' => 'Mathematics JSS 2', 'grade_label' => 'JSS 2', 'framework_code' => 'JSS', 'year_group' => 8,
            'topics' => [
                ['title' => 'Linear Equations', 'description' => 'Solve equations with variables on both sides', 'lessons' => [
                    ['title' => 'Variables on both sides', 'description' => '5x - 3 = 2x + 9', 'quiz' => $this->quiz(['5x - 3 = 2x + 9, x = ?', '4x + 1 = 2x + 11, x = ?'], [['4', '3', '5'], ['5', '4', '6']], [0, 0])],
                    ['title' => 'Word problems with equations', 'description' => 'Form and solve equations', 'quiz' => $this->quiz(['A number doubled plus 5 equals 17. The number is?', 'Three times a number minus 4 equals 11. Find the number.'], [['6', '7', '5'], ['5', '4', '6']], [0, 0])],
                ]],
                ['title' => 'Indices and Standard Form', 'description' => 'Powers and scientific notation', 'lessons' => [
                    ['title' => 'Laws of indices', 'description' => 'Multiply and divide with indices', 'quiz' => $this->quiz(['2³ × 2² = ?', 'x⁵ ÷ x² = ?'], [['2⁵', '2⁶', '4⁵'], ['x³', 'x⁷', 'x²']], [0, 0])],
                    ['title' => 'Standard form', 'description' => 'Write large/small numbers in standard form', 'quiz' => $this->quiz(['5000 in standard form = ?', '0.003 in standard form = ?'], [['5 × 10³', '5 × 10⁴', '50 × 10²'], ['3 × 10⁻³', '3 × 10³', '0.3 × 10⁻²']], [0, 0])],
                ]],
                ['title' => 'Pythagoras Theorem', 'description' => 'Right-angled triangle calculations', 'lessons' => [
                    ['title' => 'Pythagoras theorem', 'description' => 'a² + b² = c²', 'quiz' => $this->quiz(['In a right triangle, sides 3 and 4. Hypotenuse = ?', 'Pythagoras theorem: a² + b² = ___'], [['5', '6', '7'], ['c²', 'a²', 'b²']], [0, 0])],
                    ['title' => 'Applying Pythagoras', 'description' => 'Real-life problems', 'quiz' => $this->quiz(['A ladder 13m long leans against a wall. Base is 5m from wall. Height reached?', 'Sides: 6cm and 8cm. Hypotenuse = ?'], [['12m', '10m', '11m'], ['10cm', '9cm', '12cm']], [0, 0])],
                ]],
            ]];
    }

    private function jss2English(): array
    {
        return ['name' => 'English JSS 2', 'grade_label' => 'JSS 2', 'framework_code' => 'JSS', 'year_group' => 8,
            'topics' => [
                ['title' => 'Advanced Grammar', 'description' => 'Clauses, phrases and complex sentences', 'lessons' => [
                    ['title' => 'Main and subordinate clauses', 'description' => 'Although, because, when, if', 'quiz' => $this->quiz(['A main clause can stand ___ as a sentence', '"Because I was tired" is a ___ clause'], [['alone', 'never', 'sometimes'], ['subordinate', 'main', 'relative']], [0, 0])],
                    ['title' => 'Relative clauses', 'description' => 'who, which, that, whose', 'quiz' => $this->quiz(['The boy ___ won the prize is my friend.', '"Which" is used for ___, not people.'], [['who', 'which', 'that'], ['things/animals', 'people', 'places']], [0, 0])],
                ]],
                ['title' => 'Writing — Expository and Narrative', 'description' => 'Different types of writing', 'lessons' => [
                    ['title' => 'Expository writing', 'description' => 'Explain a process or concept', 'quiz' => $this->quiz(['Expository writing aims to ___', 'Sequence words like "firstly" help to ___'], [['explain/inform', 'entertain', 'persuade'], ['organise ideas', 'add humour', 'confuse the reader']], [0, 0])],
                    ['title' => 'Narrative writing techniques', 'description' => 'Show don\'t tell, dialogue, suspense', 'quiz' => $this->quiz(['"Show don\'t tell" means to ___ feelings', 'Dialogue makes a story more ___'], [['describe', 'name', 'ignore'], ['realistic', 'boring', 'confusing']], [0, 0])],
                ]],
                ['title' => 'Literature — Drama and Poetry', 'description' => 'Analyse plays and poems', 'lessons' => [
                    ['title' => 'Elements of drama', 'description' => 'Stage directions, scenes, acts', 'quiz' => $this->quiz(['A play is divided into ___ and scenes', 'Stage directions are usually written in ___'], [['acts', 'chapters', 'stanzas'], ['italics/brackets', 'bold', 'capital letters']], [0, 0])],
                    ['title' => 'Figures of speech', 'description' => 'Metaphor, personification, alliteration', 'quiz' => $this->quiz(['"Life is a journey" is an example of a ___', 'Personification gives ___ qualities to non-human things'], [['metaphor', 'simile', 'alliteration'], ['human', 'animal', 'divine']], [0, 0])],
                ]],
            ]];
    }

    private function jss3Maths(): array
    {
        return ['name' => 'Mathematics JSS 3', 'grade_label' => 'JSS 3', 'framework_code' => 'JSS', 'year_group' => 9,
            'topics' => [
                ['title' => 'BECE Preparation — Number', 'description' => 'Revise all number topics for BECE', 'lessons' => [
                    ['title' => 'Number types and operations', 'description' => 'Integers, fractions, decimals, surds', 'quiz' => $this->quiz(['√25 = ?', 'LCM of 6 and 9 = ?'], [['5', '6', '4'], ['18', '12', '9']], [0, 0])],
                    ['title' => 'Percentages and profit/loss', 'description' => 'Business mathematics', 'quiz' => $this->quiz(['Bought for ₦500, sold for ₦600. Profit %?', 'Bought for ₦800, sold for ₦700. Loss %?'], [['20%', '25%', '15%'], ['12.5%', '10%', '15%']], [0, 0])],
                ]],
                ['title' => 'BECE Preparation — Algebra', 'description' => 'Revise algebra topics', 'lessons' => [
                    ['title' => 'Simultaneous equations', 'description' => 'Solve two equations together', 'quiz' => $this->quiz(['x + y = 7 and x - y = 3. Find x.', 'If x = 5 and x + y = 7, find y.'], [['5', '4', '6'], ['2', '3', '1']], [0, 0])],
                    ['title' => 'Quadratic expressions', 'description' => 'Expand and factorise', 'quiz' => $this->quiz(['Expand (x+3)(x+2) = ?', 'Factorise x² + 5x + 6'], [['x²+5x+6', 'x²+6x+5', 'x²+5x+5'], ['(x+2)(x+3)', '(x+1)(x+6)', '(x+4)(x+2)']], [0, 0])],
                ]],
                ['title' => 'BECE Preparation — Geometry', 'description' => 'Revise geometry topics', 'lessons' => [
                    ['title' => 'Circle theorems', 'description' => 'Angles in circles', 'quiz' => $this->quiz(['Angle in a semicircle = ___°', 'Angles in the same segment are ___'], [['90', '180', '45'], ['equal', 'supplementary', 'complementary']], [0, 0])],
                    ['title' => 'Mensuration — volumes', 'description' => 'Volume of cuboid, cylinder, cone', 'quiz' => $this->quiz(['Volume of cuboid = l × w × ___', 'Volume of cylinder = πr² × ___'], [['h', 'b', 'd'], ['h', 'r', 'd']], [0, 0])],
                ]],
            ]];
    }

    private function jss3English(): array
    {
        return ['name' => 'English JSS 3', 'grade_label' => 'JSS 3', 'framework_code' => 'JSS', 'year_group' => 9,
            'topics' => [
                ['title' => 'BECE Preparation — Comprehension', 'description' => 'Advanced comprehension strategies', 'lessons' => [
                    ['title' => 'Critical reading skills', 'description' => 'Evaluate arguments in texts', 'quiz' => $this->quiz(['Critical reading means reading to ___', 'Bias in a text means the writer shows ___'], [['evaluate and question', 'memorise', 'copy'], ['one-sided view', 'balanced view', 'no opinion']], [0, 0])],
                    ['title' => 'Comprehension exam technique', 'description' => 'Score maximum marks', 'quiz' => $this->quiz(['Quote from the text means to ___', 'You should answer in ___ sentences'], [['copy exact words', 'paraphrase', 'guess'], ['complete', 'bullet', 'note-form']], [0, 0])],
                ]],
                ['title' => 'BECE Preparation — Writing', 'description' => 'Essay, letter and creative writing', 'lessons' => [
                    ['title' => 'Essay writing under exam conditions', 'description' => 'Plan, write and check', 'quiz' => $this->quiz(['Before writing, you should always ___', 'How many minutes should you spend planning?'], [['plan/outline', 'start immediately', 'read slowly'], ['5-10 minutes', '30 minutes', '0 minutes']], [0, 0])],
                    ['title' => 'Letter writing for BECE', 'description' => 'Formal and informal letters', 'quiz' => $this->quiz(['Formal letters end with "Yours faithfully" when you ___', '"Yours sincerely" is used when you ___ the person'], [['don\'t know the person\'s name', 'know them well', 'dislike them'], ['know', 'don\'t know', 'have never met']], [0, 0])],
                ]],
                ['title' => 'BECE Literature Revision', 'description' => 'Poetry, drama and prose', 'lessons' => [
                    ['title' => 'Analysing a poem', 'description' => 'Theme, tone, imagery, structure', 'quiz' => $this->quiz(['The theme of a poem is its ___', 'Tone describes the poet\'s ___ towards the subject'], [['main message', 'rhyme scheme', 'length'], ['attitude/feeling', 'language', 'structure']], [0, 0])],
                    ['title' => 'Prose analysis', 'description' => 'Characterisation and conflict', 'quiz' => $this->quiz(['Conflict in a story is the main ___', 'A protagonist is the ___ character'], [['problem/struggle', 'character', 'setting'], ['main', 'villain', 'weakest']], [0, 0])],
                ]],
            ]];
    }

    // =========================================================
    // QUIZ HELPER
    // =========================================================

    private function quiz(array $questions, array $options, array $correctIndexes): array
    {
        $quiz = [];
        foreach ($questions as $i => $question) {
            $opts    = $options[$i] ?? [];
            $correct = $correctIndexes[$i] ?? 0;
            $quiz[]  = [
                'question'      => $question,
                'options'       => $opts,
                'correct_index' => $correct,
                'correct'       => $opts[$correct] ?? null,
            ];
        }
        return $quiz;
    }
}

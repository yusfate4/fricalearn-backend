<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimpleVideoFixSeeder extends Seeder
{
    /**
     * Simple fix: Update ALL lessons with ONE reliable video per subject
     * 
     * Usage: php artisan db:seed --class=SimpleVideoFixSeeder
     */
    public function run()
    {
        $this->command->info('🔧 Fixing video links...');
        
        // Get all subjects
        $subjects = DB::table('external_subjects')->get();
        
        foreach ($subjects as $subject) {
            $videoUrl = $this->getReliableVideoUrl($subject->name);
            
            // Get all topics for this subject
            $topics = DB::table('external_topics')
                ->where('subject_id', $subject->id)
                ->get();
            
            foreach ($topics as $topic) {
                // Update ALL lessons in this topic with working video
                DB::table('external_lessons')
                    ->where('topic_id', $topic->id)
                    ->update(['video_url' => $videoUrl]);
                
                $this->command->info("  ✅ {$subject->name} - {$topic->title}");
            }
        }
        
        $this->command->info('✅ All videos fixed!');
    }
    
    /**
     * Get ONE reliable video URL per subject/year
     */
    private function getReliableVideoUrl($subjectName)
    {
        // Extract year from subject name (e.g., "Mathematics Year 8" -> 8)
        preg_match('/Year (\d+)/', $subjectName, $matches);
        $year = isset($matches[1]) ? (int) $matches[1] : 7;
        
        if (strpos($subjectName, 'Mathematics') !== false) {
            return $this->getMathsVideoUrl($year);
        } else {
            return $this->getEnglishVideoUrl($year);
        }
    }
    
    /**
     * Get reliable Maths video based on year
     */
    private function getMathsVideoUrl($year)
    {
        // Use Corbettmaths main playlist videos
        $videoMap = [
            1 => 'https://www.youtube.com/watch?v=Ftati8iGQcs',  // KS1 Maths
            2 => 'https://www.youtube.com/watch?v=Ftati8iGQcs',  // KS1 Maths
            3 => 'https://www.youtube.com/watch?v=RxRHr1rpXLo',  // KS2 Maths
            4 => 'https://www.youtube.com/watch?v=RxRHr1rpXLo',  // KS2 Maths
            5 => 'https://www.youtube.com/watch?v=RxRHr1rpXLo',  // KS2 Maths
            6 => 'https://www.youtube.com/watch?v=RxRHr1rpXLo',  // KS2 Maths
            7 => 'https://www.youtube.com/watch?v=0rlIfHOK0HI',  // Corbettmaths - Angles
            8 => 'https://www.youtube.com/watch?v=FDxD7qB5pno',  // Corbettmaths - Equations
            9 => 'https://www.youtube.com/watch?v=c55_3bChcUY',  // Corbettmaths - Pythagoras
            10 => 'https://www.youtube.com/watch?v=5OAXJUdoQ9I', // Corbettmaths - Circle Theorems
            11 => 'https://www.youtube.com/watch?v=I_rUPxJOlz0', // Corbettmaths - Simultaneous
        ];
        
        return $videoMap[$year] ?? 'https://www.youtube.com/watch?v=0rlIfHOK0HI';
    }
    
    /**
     * Get reliable English video based on year
     */
    private function getEnglishVideoUrl($year)
    {
        $videoMap = [
            1 => 'https://www.youtube.com/watch?v=BELlZKpi1Zs',  // Phonics
            2 => 'https://www.youtube.com/watch?v=BELlZKpi1Zs',  // Phonics
            3 => 'https://www.youtube.com/watch?v=IZJpVrJ7eMI',  // Grammar
            4 => 'https://www.youtube.com/watch?v=8mQjGGZCF18',  // Writing
            5 => 'https://www.youtube.com/watch?v=y-jzp5kLdps',  // Reading
            6 => 'https://www.youtube.com/watch?v=y-jzp5kLdps',  // Reading
            7 => 'https://www.youtube.com/watch?v=LZa03BuCELk',  // Poetry
            8 => 'https://www.youtube.com/watch?v=Yx-rvJ5HqUk',  // Shakespeare
            9 => 'https://www.youtube.com/watch?v=Yx-rvJ5HqUk',  // Shakespeare
            10 => 'https://www.youtube.com/watch?v=R8xurCAu1KI', // Novel Analysis
            11 => 'https://www.youtube.com/watch?v=R8xurCAu1KI', // Novel Analysis
        ];
        
        return $videoMap[$year] ?? 'https://www.youtube.com/watch?v=IZJpVrJ7eMI';
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Models\Course;
use App\Models\ExternalSubject;
use Illuminate\Support\Facades\Log;

class AutoEnrollmentService
{
    /**
     * Auto-enroll user in Maths or English when they enroll in African language
     * 
     * @param User $user The student being enrolled
     * @param Course $course The course they're enrolling in
     * @return ExternalSubject|null The assigned subject, or null if not applicable
     */
    public function enrollUserInExternalSubject(User $user, Course $course)
    {
        // Only trigger for African language courses
        $africanLanguages = ['Yoruba', 'Hausa', 'Igbo'];
        
        if (!in_array($course->title, $africanLanguages)) {
            Log::info("Auto-enrollment skipped: {$course->title} is not an African language course");
            return null;
        }

        // Check if user already has external subject
        if ($user->externalSubjects()->exists()) {
            Log::info("Auto-enrollment skipped: User {$user->id} already enrolled in external subject");
            return null;
        }

        // Determine year group based on student age
        $yearGroup = $this->determineYearGroup($user);

        // Get available subjects for this year group
        $subjects = ExternalSubject::whereIn('name', ['Maths', 'English'])
                                    ->where('year_group', $yearGroup)
                                    ->get();

        if ($subjects->isEmpty()) {
            Log::warning("No external subjects found for year group {$yearGroup}");
            return null;
        }

        // Randomly select Maths or English
        $subject = $subjects->random();

        // Enroll user
        $user->externalSubjects()->attach($subject->id, [
            'enrolled_at' => now(),
            'progress_percentage' => 0
        ]);

        Log::info("Auto-enrolled user {$user->id} ({$user->name}) in {$subject->name} Year {$yearGroup}");

        return $subject;
    }

    /**
     * Determine appropriate year group based on user age/profile
     * 
     * @param User $user
     * @return int Year group (1-11)
     */
    private function determineYearGroup(User $user)
    {
        // Try to get age from student profile
        if ($user->studentProfile && $user->studentProfile->date_of_birth) {
            $dob = \Carbon\Carbon::parse($user->studentProfile->date_of_birth);
            $age = $dob->age;
            
            // Map age to UK year groups
            $ageToYear = [
                5 => 1, 6 => 1,   // Year 1 (Ages 5-6)
                7 => 2, 8 => 3,   // Year 2-3 (Ages 7-8)
                9 => 4, 10 => 5,  // Year 4-5 (Ages 9-10)
                11 => 6, 12 => 7, // Year 6-7 (Ages 11-12)
                13 => 8, 14 => 9, // Year 8-9 (Ages 13-14)
                15 => 10, 16 => 11 // Year 10-11 (Ages 15-16)
            ];
            
            if (isset($ageToYear[$age])) {
                Log::info("Determined year group {$ageToYear[$age]} from age {$age}");
                return $ageToYear[$age];
            }
        }

        // Map grade level to year group
        if ($user->studentProfile && $user->studentProfile->grade_level) {
            $gradeMap = [
                'Beginners' => 6,     // Year 6 (KS2/KS3 transition)
                'Intermediate' => 7,  // Year 7 (KS3 start)
                'Advance' => 8        // Year 8 (KS3)
            ];
            
            $yearFromGrade = $gradeMap[$user->studentProfile->grade_level] ?? 7;
            Log::info("Determined year group {$yearFromGrade} from grade level {$user->studentProfile->grade_level}");
            return $yearFromGrade;
        }

        // Default to Year 7 (most common secondary entry point)
        Log::info("Using default year group 7 (no age/grade data available)");
        return 7;
    }
}
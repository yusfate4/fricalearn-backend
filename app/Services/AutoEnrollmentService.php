<?php

namespace App\Services;

use App\Models\EnrollmentPayment;
use App\Models\User;
use App\Models\ExternalSubject;
use App\Models\UserExternalSubjectEnrollment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoEnrollmentService
{
    /**
     * Main enrollment orchestrator
     * Enrolls student in all courses indicated by the payment record
     */
    public function enrollStudent(EnrollmentPayment $payment)
    {
        $student = User::find($payment->user_id);
        
        if (!$student) {
            throw new \Exception('Student not found');
        }

        Log::info('Auto-enrolling student', [
            'student_id' => $student->id,
            'student_name' => $student->name,
            'payment_id' => $payment->id
        ]);

        // Enroll in Maths (UK Curriculum - External Subjects)
        if ($payment->includes_maths && $payment->maths_grade) {
            $this->enrollInMaths($student, $payment->maths_grade);
        }

        // Enroll in English (UK Curriculum - External Subjects)
        if ($payment->includes_english && $payment->english_grade) {
            $this->enrollInEnglish($student, $payment->english_grade);
        }

        // Enroll in Languages (Regular Courses)
        if ($payment->includes_yoruba) {
            $this->enrollInLanguage($student, 'Yoruba');
        }

        if ($payment->includes_hausa) {
            $this->enrollInLanguage($student, 'Hausa');
        }

        if ($payment->includes_igbo) {
            $this->enrollInLanguage($student, 'Igbo');
        }

        Log::info('Auto-enrollment completed', [
            'student_id' => $student->id,
            'courses_enrolled' => [
                'maths' => $payment->includes_maths,
                'english' => $payment->includes_english,
                'yoruba' => $payment->includes_yoruba,
                'hausa' => $payment->includes_hausa,
                'igbo' => $payment->includes_igbo,
            ]
        ]);

        return true;
    }

    /**
     * Enroll student in UK Mathematics curriculum
     * Uses ExternalSubject and UserExternalSubjectEnrollment
     */
    private function enrollInMaths($student, $grade)
    {
        try {
            // Find or create Mathematics subject for this grade/year
            $subject = ExternalSubject::firstOrCreate([
                'name' => "Mathematics Year {$grade}",
                'year_group' => $grade,
            ], [
                'key_stage' => $this->getKeyStage($grade),
                'source' => 'UK National Curriculum',
                'description' => "UK National Curriculum Mathematics for Year {$grade}",
            ]);

            // Enroll student in this subject
            UserExternalSubjectEnrollment::firstOrCreate([
                'user_id' => $student->id,
                'external_subject_id' => $subject->id,
            ], [
                'enrolled_at' => now(),
                'progress_percentage' => 0,
                'completed_at' => null,
            ]);

            Log::info('Enrolled in Mathematics', [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'grade' => $grade
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to enroll in Maths', [
                'student_id' => $student->id,
                'grade' => $grade,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enroll student in UK English curriculum
     * Uses ExternalSubject and UserExternalSubjectEnrollment
     */
    private function enrollInEnglish($student, $grade)
    {
        try {
            // Find or create English subject for this grade/year
            $subject = ExternalSubject::firstOrCreate([
                'name' => "English Year {$grade}",
                'year_group' => $grade,
            ], [
                'key_stage' => $this->getKeyStage($grade),
                'source' => 'UK National Curriculum',
                'description' => "UK National Curriculum English for Year {$grade}",
            ]);

            // Enroll student in this subject
            UserExternalSubjectEnrollment::firstOrCreate([
                'user_id' => $student->id,
                'external_subject_id' => $subject->id,
            ], [
                'enrolled_at' => now(),
                'progress_percentage' => 0,
                'completed_at' => null,
            ]);

            Log::info('Enrolled in English', [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'grade' => $grade
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to enroll in English', [
                'student_id' => $student->id,
                'grade' => $grade,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enroll student in language course (Yoruba/Hausa/Igbo)
     * Uses regular Course and CourseEnrollment models
     */
    private function enrollInLanguage($student, $language)
    {
        try {
            // Find the language course in the courses table
            $course = Course::where('title', 'LIKE', "%{$language}%")
                ->orWhere('title', 'LIKE', "%{$language} Language%")
                ->first();

            if (!$course) {
                Log::warning("Language course not found: {$language}", [
                    'student_id' => $student->id
                ]);
                return;
            }

            // Enroll student in the course
            CourseEnrollment::firstOrCreate([
                'user_id' => $student->id,
                'course_id' => $course->id,
            ], [
                'enrolled_at' => now(),
                'status' => 'active',
                'progress_percentage' => 0,
            ]);

            Log::info("Enrolled in {$language}", [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_name' => $course->title
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to enroll in {$language}", [
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Map UK year group to Key Stage
     * 
     * UK Education System:
     * - KS1: Years 1-2 (Ages 5-7)
     * - KS2: Years 3-6 (Ages 7-11)
     * - KS3: Years 7-9 (Ages 11-14)
     * - KS4: Years 10-11 (Ages 14-16)
     */
    private function getKeyStage($year)
    {
        if ($year <= 2) return 'KS1';
        if ($year <= 6) return 'KS2';
        if ($year <= 9) return 'KS3';
        return 'KS4'; // Years 10-11
    }

    /**
     * Revoke all enrollments for a student (used when payment is rejected)
     */
    public function revokeEnrollments($studentId)
    {
        try {
            DB::beginTransaction();

            // Delete external subject enrollments (Maths/English)
            UserExternalSubjectEnrollment::where('user_id', $studentId)->delete();

            // Delete course enrollments (Languages)
            CourseEnrollment::where('user_id', $studentId)->delete();

            DB::commit();

            Log::info('Enrollments revoked', ['student_id' => $studentId]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revoke enrollments', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

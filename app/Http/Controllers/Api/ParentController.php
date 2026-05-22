<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentPayment;
use App\Models\Course;

class ParentController extends Controller
{
    /**
     * 🧠 Helper: Unified ID fetcher
     * This ensures we find children via the pivot table OR the direct parent_id column.
     */
    private function getLinkedChildIds($parent)
    {
        $pivotIds = $parent->children()->pluck('users.id')->toArray();
        $directIds = User::where('parent_id', $parent->id)->pluck('id')->toArray();
        
        return array_unique(array_merge($pivotIds, $directIds));
    }

    /**
     * 📊 Master Dashboard Data
     * Fix: Fetches BOTH language courses AND UK Curriculum enrollments
     */
    public function getDashboardData(Request $request)
    {
        $parent = $request->user();
        $childIds = $this->getLinkedChildIds($parent);

        // 1. Fetch Children with Profiles
        $children = User::whereIn('id', $childIds)->with(['studentProfile'])->get();

        // 2. Fetch Language Course Enrollments
        $courseEnrollments = CourseEnrollment::with(['course'])
            ->whereIn('student_id', $childIds)
            ->where('status', 'active')
            ->get()
            ->map(function($enrollment) {
                return [
                    'student_id' => $enrollment->student_id,
                    'course_id' => $enrollment->course_id,
                    'course' => $enrollment->course ? [
                        'id' => $enrollment->course->id,
                        'title' => $enrollment->course->title,
                    ] : null,
                    'type' => 'language_course'
                ];
            });

        // 3. Fetch UK Curriculum Enrollments (Maths/English)
        $externalEnrollments = DB::table('user_external_subject_enrollments')
            ->whereIn('user_id', $childIds)
            ->join('external_subjects', 'external_subjects.id', '=', 'user_external_subject_enrollments.external_subject_id')
            ->select(
                'user_external_subject_enrollments.user_id as student_id',
                'user_external_subject_enrollments.external_subject_id',
                'external_subjects.name as subject_name',
                'external_subjects.id as subject_id'
            )
            ->get()
            ->map(function($enrollment) {
                return [
                    'student_id' => $enrollment->student_id,
                    'external_subject_id' => $enrollment->external_subject_id,
                    'external_subject' => [
                        'id' => $enrollment->subject_id,
                        'name' => $enrollment->subject_name,
                    ],
                    'type' => 'uk_curriculum'
                ];
            });

        // 4. Merge both types of enrollments
        $activeEnrollments = $courseEnrollments->merge($externalEnrollments);

        // 5. Fetch Pending Payments (To show the "Awaiting Verification" section)
        $pendingPayments = EnrollmentPayment::with(['course'])
            ->where('parent_id', $parent->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        // 6. Add track info to children (for backward compatibility)
        $childrenWithTracks = $children->map(function($child) use ($activeEnrollments) {
            // Find enrollments for this child
            $childEnrollments = $activeEnrollments->where('student_id', $child->id);
            
            if ($childEnrollments->isNotEmpty()) {
                // Get first enrollment for current_track field
                $firstEnrollment = $childEnrollments->first();
                $child->current_track = $firstEnrollment['course']['title'] ?? 
                                       $firstEnrollment['external_subject']['name'] ?? 
                                       'Active';
            } else {
                $child->current_track = $child->studentProfile->learning_language ?? 'No Track';
            }
            return $child;
        });

        return response()->json([
            'parent_name'        => $parent->name,
            'active_enrollments' => $activeEnrollments->values(), // Reset array keys
            'pending_payments'   => $pendingPayments,
            'children'           => $childrenWithTracks, 
            'stats' => [
                'active_courses' => $activeEnrollments->count(),
                'pending_count'  => $pendingPayments->count(),
            ]
        ]);
    }

    /**
     * 🚀 Fetch enrolled courses for a specific child
     */
    public function getCourses(Request $request)
    {
        $studentId = $request->query('student_id');

        if (!$studentId) {
            return response()->json(['error' => 'No student_id in request'], 400);
        }

        $courseIds = DB::table('course_enrollments')
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('course_id')
            ->toArray();

        $courses = Course::whereIn('id', $courseIds)->get();

        return response()->json([
            'courses' => $courses
        ]);
    }

    /**
     * 👶 Create a NEW student account
     */
    public function registerChild(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date_of_birth' => 'required|string', 
            'grade_level' => 'required|in:Beginners,Intermediate,Advance',
            'learning_language' => 'required|in:Yoruba,Hausa,Igbo,English,Maths',
            'relationship' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $student = User::create([
                'name' => trim($validated['name']),
                'email' => strtolower(str_replace(' ', '', $validated['name'])) . rand(100, 999) . '@fricalearn.com',
                'password' => Hash::make('student123'),
                'role' => 'student',
                'parent_id' => $request->user()->id, 
            ]);

            $student->studentProfile()->create([
                'date_of_birth' => $validated['date_of_birth'],
                'grade_level' => $validated['grade_level'],
                'learning_language' => $validated['learning_language'],
                'total_points' => 0,
                'total_coins' => 0,
                'rank' => 'Akeko'
            ]);

            $request->user()->children()->syncWithoutDetaching([
                $student->id => ['relationship' => $validated['relationship'] ?? 'Parent']
            ]);

            return response()->json(['message' => 'Child registered successfully!', 'student' => $student], 201);
        });
    }

    /**
     * 🚪 Switch context to a child (Impersonation)
     */
    public function switchToChild(Request $request, $childId)
    {
        $parent = $request->user();
        $childIds = $this->getLinkedChildIds($parent);

        if (!in_array($childId, $childIds)) {
            return response()->json(['message' => 'Unauthorized student access'], 403);
        }

        $child = User::with('studentProfile')->findOrFail($childId);

        $activeEnrollment = CourseEnrollment::where('student_id', $childId)
            ->where('status', 'active')
            ->first();

        $token = $child->createToken('parent_switched_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $child,
            'active_course_id' => $activeEnrollment ? $activeEnrollment->course_id : null
        ]);
    }

    /**
     * 👤 THE FIX: Get individual child profile (For Dashboard Sync)
     * Renamed from getActiveStudentProfile to match api.php and React request
     */
    public function getActiveStudent($id)
    {
        try {
            $parent = auth()->user();
            $childIds = $this->getLinkedChildIds($parent);

            // Security check: Make sure this parent owns this child
            if (!in_array($id, $childIds) && $parent->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized access to this student profile.'], 403);
            }

            // Fetch the student with their profile and return the whole object
            $student = User::with('studentProfile')->findOrFail($id);

            return response()->json($student);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Dashboard Sync Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 📋 Get just the list of children
     */
    public function getChildren(Request $request)
    {
        $parent = $request->user();
        $childIds = $this->getLinkedChildIds($parent);

        $children = User::whereIn('id', $childIds)
            ->with(['studentProfile'])
            ->get();

        return response()->json($children);
    }
}
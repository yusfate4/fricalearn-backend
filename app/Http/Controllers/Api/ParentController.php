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
     */
    private function getLinkedChildIds($parent)
    {
        $pivotIds = $parent->children()->pluck('users.id')->toArray();
        $directIds = User::where('parent_id', $parent->id)->pluck('id')->toArray();
        
        return array_unique(array_merge($pivotIds, $directIds));
    }

    /**
     * 📊 Master Dashboard Data
     * Dynamically determines the "Track" based on active enrollment.
     */
    public function getDashboardData(Request $request)
    {
        $parent = $request->user();
        $childIds = $this->getLinkedChildIds($parent);

        // 1. Fetch Children with Profiles
        $children = User::whereIn('id', $childIds)->with(['studentProfile'])->get();

        // 2. Fetch Active Enrollments with Course titles
        $activeEnrollments = CourseEnrollment::with(['course'])
            ->whereIn('student_id', $childIds)
            ->where('status', 'active')
            ->get();

        // 3. Fetch Pending Payments for the Parent
        $pendingPayments = EnrollmentPayment::with(['course'])
            ->where('parent_id', $parent->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        // 🚀 THE LOGIC FIX: Map the Course Title as the "Track"
        $childrenWithTracks = $children->map(function($child) use ($activeEnrollments) {
            $enrollment = $activeEnrollments->where('student_id', $child->id)->first();
            
            // Priority: Active Course Name > Profile Language > Default
            if ($enrollment && $enrollment->course) {
                $child->current_track = $enrollment->course->title; 
            } else {
                $child->current_track = $child->studentProfile->learning_language ?? 'No Track';
            }
            return $child;
        });

        return response()->json([
            'parent_name'        => $parent->name,
            'active_enrollments' => $activeEnrollments,
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
            'debug' => [
                'searching_for_student' => $studentId,
                'found_enrollment_ids' => $courseIds,
                'total_courses_found' => $courses->count()
            ],
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
     * 👤 Get individual child profile
     */
    public function getActiveStudentProfile($id)
    {
        $parent = auth()->user();
        $childIds = $this->getLinkedChildIds($parent);

        if (!in_array($id, $childIds) && $parent->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $student = User::with('studentProfile')->findOrFail($id);

        return response()->json([
            'id' => $student->id,
            'name' => $student->name,
            'student_profile' => $student->studentProfile
        ]);
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
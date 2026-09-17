<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Question;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\StudentProfile;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * 1. ADMIN DASHBOARD OVERVIEW (Legacy Index)
     * Used for the "Control Room" main stats cards
     */
    public function index() 
    {
        return response()->json([
            'avg_score' => 88, 
            'active_count' => User::where('role', 'student')->count(),
            'completed_count' => 15,
            'total_lessons' => Lesson::count(),
            'total_courses' => Course::count(),
            'recent_students' => User::where('role', 'student')
                ->with('studentProfile')
                ->latest()
                ->take(5)
                ->get()
        ]);
    }

    /**
     * 2. DETAILED STUDENT STATS
     * Refined for Student Portal & Parent Impersonation
     */
    public function studentStats(Request $request, $userId = null)
    {
        $user = $request->user();
        $headerStudentId = $request->header('X-Active-Student-Id');
        
        // Determing Target ID
        if ($userId && ($user->role === 'admin' || $user->is_admin == 1)) {
            $targetId = $userId;
        } elseif ($headerStudentId && $user->role === 'parent') {
            $isChild = $user->children()->where('child_id', $headerStudentId)->exists();
            $targetId = $isChild ? $headerStudentId : $user->id;
        } else {
            $targetId = $user->id;
        }

        $student = User::with(['studentProfile', 'progressRecords.lesson'])->findOrFail($targetId);

        return response()->json([
            'student_name'    => $student->name,
            'total_lessons'   => $student->progressRecords()->where('status', 'completed')->count(),
            'avg_score'       => $student->progressRecords()->avg('score') ?? 0,
            'total_points'    => $student->studentProfile->total_points ?? 0,
            'total_coins'     => $student->studentProfile->total_coins ?? 0,
            'recent_activity' => $student->progressRecords()
                ->with('lesson')
                ->latest()
                ->take(5)
                ->get()
        ]);
    }

    /**
     * 3. PUBLIC STUDENT STATS (For Shareable Links)
     */
    public function publicStudentStats($studentId)
    {
        $user = User::with(['studentProfile', 'progressRecords.lesson'])->findOrFail($studentId);

        return response()->json([
            'student_name'  => $user->name,
            'total_lessons' => $user->progressRecords()->where('status', 'completed')->count(),
            'avg_score'     => $user->progressRecords()->avg('score') ?? 0,
            'total_points'  => $user->studentProfile->total_points ?? 0,
            'total_coins'   => $user->studentProfile->total_coins ?? 0,
            'recent_activity' => $user->progressRecords()
                ->with('lesson')
                ->latest()
                ->take(3)
                ->get()
        ]);
    }

    /**
     * 4. GLOBAL STAFF STATS
     * Used by both AdminDashboard and TutorDashboard
     */
    public function adminStats()
    {
        // 🚀 THE FIX: Allow both Admins AND Tutors
        $user = auth()->user();
        $isStaff = $user->role === 'admin' || $user->role === 'tutor' || (int)$user->is_admin === 1;

        if (!$isStaff) {
            return response()->json(['message' => 'Unauthorized Staff Access Only'], 403);
        }

        return response()->json([
            'total_students'      => User::where('role', 'student')->count(),
            'total_courses'       => Course::count(),
            'total_lessons'       => Lesson::count(),
            'total_live_classes'  => LiveClass::count(), // 👈 Added for Tutor Dashboard
            'total_coins'         => StudentProfile::sum('total_coins') ?? 0,
        ]);
    }
}
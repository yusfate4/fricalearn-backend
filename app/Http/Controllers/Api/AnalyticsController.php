<?php

namespace App\Http\Controllers\Api; // 👈 Moved to Api namespace to match your routes

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Question;
use App\Models\Lesson;
use App\Models\Course;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * 1. ADMIN DASHBOARD OVERVIEW
     * Used for the "Control Room" main stats cards
     */
    public function index() 
    {
        return response()->json([
            'avg_score' => 88, // Placeholder for now
            'active_count' => User::where('is_admin', 0)->count(),
            'completed_count' => 15,
            'total_lessons' => Lesson::count(),
            'total_courses' => Course::count(),
            'recent_students' => User::where('is_admin', 0)
                ->with('studentProfile')
                ->latest()
                ->take(5)
                ->get()
        ]);
    }

    /**
     * 2. DETAILED STUDENT STATS (Private)
     * Used by Student Portal & Admin User Registry
     */
  /**
     * 2. DETAILED STUDENT STATS (Refined for Parent Impersonation)
     * Used by Student Portal & Parents viewing as Students
     */
    public function studentStats(Request $request, $userId = null)
    {
        $user = $request->user();
        
        // 🚀 THE SWITCHER LOGIC
        // 1. If an Admin passes an ID in the URL, use that.
        // 2. If a Parent sends the 'X-Active-Student-Id' header, use that.
        // 3. Otherwise, use the logged-in user's own ID (Standard Student login).
        
        $headerStudentId = $request->header('X-Active-Student-Id');
        
        if ($userId && $user->is_admin) {
            $targetId = $userId;
        } elseif ($headerStudentId && $user->role === 'parent') {
            // 🔒 Security: Verify this student is actually linked to this parent
            $isChild = $user->children()->where('child_id', $headerStudentId)->exists();
            $targetId = $isChild ? $headerStudentId : $user->id;
        } else {
            $targetId = $user->id;
        }

        // Fetch the data for the determined Target ID
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
     * 3. PUBLIC STUDENT STATS (For Parents)
     * No login required, but limited data for privacy
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
                ->take(3) // Parents only see top 3 latest achievements
                ->get()
        ]);
    }

    public function adminStats()
{
    // Ensure only admins can see this
    if (!auth()->user()->is_admin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    return response()->json([
        'total_students' => \App\Models\User::where('is_admin', 0)->count(),
        'total_courses'  => \App\Models\Course::count(),
        'total_lessons'  => \App\Models\Lesson::count(),
        'total_coins'    => \App\Models\StudentProfile::sum('total_coins'),
    ]);
}
}
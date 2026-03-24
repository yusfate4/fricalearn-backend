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
    public function studentStats(Request $request, $userId = null)
    {
        // Safety: If an ID is provided, check if the person asking is an Admin
        if ($userId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Use the ID from URL, otherwise use the logged-in student
        $targetId = $userId ?? $request->user()->id;
        $user = User::with(['studentProfile', 'progressRecords.lesson'])->findOrFail($targetId);

        return response()->json([
            'student_name'  => $user->name,
            'total_lessons' => $user->progressRecords()->where('status', 'completed')->count(),
            'avg_score'     => $user->progressRecords()->avg('score') ?? 0,
            'total_points'  => $user->studentProfile->total_points ?? 0,
            'total_coins'   => $user->studentProfile->total_coins ?? 0,
            'recent_activity' => $user->progressRecords()
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
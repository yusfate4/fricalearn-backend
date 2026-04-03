<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    /**
     * 🎓 Smart Library Index
     * Handles: /api/courses
     */
  public function index(Request $request)
{
    try {
        $user = $request->user();
        
        // 🕵️ Get ID from Header or Query Param (SelectCourses uses Header)
        $activeStudentId = $request->header('X-Active-Student-Id') ?: $request->query('student_id');

        // 👑 1. ADMIN: See everything
        if ($user && ($user->role === 'admin' || $user->is_admin == 1)) {
            return response()->json(Course::withCount(['modules'])->latest()->get());
        }

        /**
         * 🛒 2. PARENT SHOPPING / GENERAL VIEW:
         * Triggered if: 
         * - Role is parent AND no active student context is provided.
         * - OR Header 'X-Active-Student-Id' is explicitly sent as empty (like in your React code).
         */
        if (!$activeStudentId || ($user && $user->role === 'parent' && empty($activeStudentId))) {
            $catalog = Course::where('is_published', true)
                ->withCount(['modules'])
                ->latest()
                ->get();
                
            return response()->json($catalog);
        }

        // 🛡️ 3. STUDENT / CLASSROOM VIEW: Only show PAID/ACTIVE courses
        $enrolledCourseIds = \App\Models\CourseEnrollment::where('student_id', $activeStudentId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->pluck('course_id');

        return response()->json(
            Course::whereIn('id', $enrolledCourseIds)
                ->where('is_published', true)
                ->withCount(['modules'])
                ->latest()
                ->get()
        );
        
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    /**
     * 👨‍👩‍👧‍👦 Parent: Fetch courses for browsing or impersonation
     * Handles: /api/parent/courses
     */
    public function getParentCourses(Request $request)
    {
        try {
            $activeStudentId = $request->header('X-Active-Student-Id') ?: $request->query('student_id');

            if ($activeStudentId) {
                $enrolledCourseIds = CourseEnrollment::where('student_id', $activeStudentId)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->pluck('course_id');

                return response()->json(
                    Course::whereIn('id', $enrolledCourseIds)
                        ->where('is_published', true)
                        ->withCount('modules')
                        ->get()
                );
            }

            return response()->json(Course::where('is_published', true)->withCount('modules')->get());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 📖 Show a specific course
     * ✅ ONLY ONE VERSION OF THIS ALLOWED
     */
 public function show($id)
{
    try {
        // 🚀 THE FIX: We use 'order_index' instead of 'order' 
        // to match your specific database column name.
        $course = Course::with([
            'modules' => function($query) use ($id) {
                $query->where('course_id', $id)->orderBy('order_index', 'asc');
            },
            'modules.lessons'
        ])->find($id);

        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        return response()->json($course);
        
    } catch (\Exception $e) {
        // 🕵️ This will help you catch any other hidden SQL errors
        return response()->json([
            'error' => 'Database Query Error',
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * 🔐 Admin: Store
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:courses',
            'category' => 'required|string', 
            'subject' => 'required|string',
            'level' => 'required|string',
            'description' => 'required|string',
            'price_ngn' => 'required|numeric',
            'price_gbp' => 'required|numeric',
            'image' => 'required|image|max:5120', 
        ]);

        $imagePath = $request->hasFile('image') ? $request->file('image')->store('courses', 'public') : null;

        $course = Course::create(array_merge($validated, [
            'thumbnail_url' => $imagePath,
            'created_by' => auth()->id(),
            'is_published' => true,
        ]));

        return response()->json($course, 201);
    }

    /**
     * 📝 Admin: Update
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $data = $request->except('image');

        if ($request->hasFile('image')) {
            if ($course->thumbnail_url) Storage::disk('public')->delete($course->thumbnail_url);
            $data['thumbnail_url'] = $request->file('image')->store('courses', 'public');
        }

        $course->update($data); 
        return response()->json(['message' => 'Course updated successfully', 'course' => $course]);
    }

    /**
     * 🗑️ Admin: Delete
     */
    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        if ($course->thumbnail_url) Storage::disk('public')->delete($course->thumbnail_url);
        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    /**
     * Display a listing of courses for students (LMS-07)
     */
    public function index(Request $request)
    {
        // We use 'withCount' to show how many lessons are in each course on the dashboard
        $query = Course::withCount(['modules'])
            ->published();

        if ($request->has('subject')) {
            $query->bySubject($request->subject);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $courses = $query->latest()->paginate(12);

        return response()->json($courses);
    }

    /**
     * Store a newly created course (LMS-01)
     */
    public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255|unique:courses',
        'category' => 'required|string',
        'subject' => 'required|string',
        'level' => 'nullable|string',
        'description' => 'required|string',
        'thumbnail_url' => 'nullable|string',
    ]);

    $course = Course::create([
        'title' => $validated['title'],
        'category' => $validated['category'],
        'subject' => $validated['subject'],
        'level' => $validated['level'] ?? 'All Ages',
        'description' => $validated['description'],
        // The ?? 'URL' part stops the "Undefined array key" error!
        'thumbnail_url' => $validated['thumbnail_url'] ?? 'https://via.placeholder.com/300', 
        'created_by' => $request->user()->id,
        'is_published' => true,
    ]);

    return response()->json($course, 201);
}

    /**
     * Show a specific course with its modules and lessons (LMS-03)
     */
    public function show($id)
    {
        $course = Course::with(['modules.lessons'])->find($id);

        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        return response()->json($course);
    }

    public function update(Request $request, $id)
{
    $course = Course::findOrFail($id);
    $course->update($request->all()); // Or use $request->validated() if you have a form request
    return response()->json($course);
}

    /**
     * Enroll a student in a course (HL-05)
     */
    public function enroll(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $user = $request->user();

        // Check if already enrolled
        $exists = CourseEnrollment::where('course_id', $course->id)
            ->where('student_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already enrolled'], 400);
        }

        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id,
            'student_id' => $user->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        return response()->json(['message' => 'Successfully enrolled!', 'enrollment' => $enrollment], 201);
    }
}
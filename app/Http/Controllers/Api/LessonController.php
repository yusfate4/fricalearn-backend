<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\ProgressRecord;
use App\Models\Attachment; // Ensure you created this model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class LessonController extends Controller
{
    /**
     * Admin: List all lessons for management (LMS-09)
     */
    public function index()
    {
        return response()->json(Lesson::with('module.course')->latest()->get());
    }

    /**
     * Fetch a specific lesson, its questions, and attachments (LMS-07)
     */
    public function show($id)
    {
        // Added 'attachments' to the eager load so students can download PDFs/PPTs
        $lesson = Lesson::with(['questions', 'attachments'])->find($id);

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        return response()->json($lesson);
    }

    /**
     * Admin: Create a new lesson with file uploads (LMS-02, LMS-10)
     */
    public function store(Request $request)
{
    if (!$request->user()->is_admin) {
        return response()->json(['message' => 'Oda! Admin only.'], 403);
    }

    $validated = $request->validate([
        'course_id'   => 'required|exists:courses,id',
        'title'       => 'required|string|max:255',
        'content'     => 'required|string', // Matches your Model!
        'video_url'   => 'nullable|url',
        'files.*'     => 'file|max:20480', 
    ]);

    // 1. Handle the Module Fallback
    $moduleId = $request->module_id;
    if (!$moduleId) {
        $defaultModule = \App\Models\Module::firstOrCreate(
            ['course_id' => $validated['course_id'], 'title' => 'General Lessons'],
            ['order_index' => 1]
        );
        $moduleId = $defaultModule->id;
    }

    // 2. Create the Lesson
    $lesson = Lesson::create([
        'course_id'    => $validated['course_id'],
        'module_id'    => $moduleId,
        'title'        => $validated['title'],
        'content'      => $validated['content'], // Updated from 'content_body'
        'video_url'    => $validated['video_url'],
        'order_index'  => 0,
        'is_published' => true,
    ]);

    // 3. Handle files (keeping your existing logic)
    if ($request->hasFile('files')) {
        foreach ($request->file('files') as $file) {
            $path = $file->store('lesson_resources', 'public');
            \App\Models\Attachment::create([
                'lesson_id' => $lesson->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => \Illuminate\Support\Facades\Storage::url($path),
                'file_type' => $file->getClientOriginalExtension(),
            ]);
        }
    }

    return response()->json($lesson->load('attachments'), 201);
}
    /**
     * Mark a lesson as started (LMS-06)
     */
    public function start(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);
        $user = $request->user();

        $progress = ProgressRecord::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        return response()->json(['message' => 'Lesson started', 'progress' => $progress]);
    }

    /**
     * Mark a lesson as completed and award points (GAM-01)
     */
    public function complete(Request $request, $id)
{
    $user = $request->user();
    
    // 1. Ensure the student has a profile to receive points (GAM-01)
    $profile = $user->studentProfile;
    if (!$profile) {
        // Create one on the fly if it's missing so the progress saves
        $profile = \App\Models\StudentProfile::create([
            'user_id' => $user->id,
            'language' => 'Yoruba', // Default for pilot
            'total_points' => 0
        ]);
    }

    // 2. Award Points (Gamification Mechanism) 
    $profile->increment('total_points', 50);

    // 3. Update the Centralized Progress Tracker (LMS-06) 
    \App\Models\ProgressRecord::updateOrCreate(
        ['user_id' => $user->id, 'lesson_id' => $id],
        ['status' => 'completed', 'completed_at' => now()]
    );

    return response()->json([
        'message' => 'O ku ise! (Well done!)',
        'total_points' => $profile->total_points
    ]);
}
}
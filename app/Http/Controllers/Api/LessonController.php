<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\ProgressRecord;
use App\Models\Attachment;
use App\Models\LessonContent;
use App\Models\StudentProfile;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    /**
     * Admin: List all lessons for management
     */
    public function index()
    {
        return response()->json(Lesson::with('module.course')->latest()->get());
    }

    /**
     * Fetch a specific lesson and its questions (LMS-07)
     * Now includes practice_word for the Speak & Earn feature
     */
    public function show($id)
    {
        $lesson = Lesson::with(['questions', 'attachments', 'contents'])->find($id);

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        return response()->json($lesson);
    }

    /**
     * Admin: Create a new lesson
     */
    public function store(Request $request)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Oda! Admin only.'], 403);
        }

        $validated = $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'title'         => 'required|string|max:255',
            'practice_word' => 'nullable|string|max:255', // 👈 Added for AI Pronunciation
            'content'       => 'required|string', 
            'video_url'     => 'nullable|url',
            'files.*'       => 'file|max:20480', 
        ]);

        // Default to a general module if none provided
        $moduleId = $request->module_id;
        if (!$moduleId) {
            $defaultModule = \App\Models\Module::firstOrCreate(
                ['course_id' => $validated['course_id'], 'title' => 'General Lessons'],
                ['order_index' => 1]
            );
            $moduleId = $defaultModule->id;
        }

        $lesson = Lesson::create([
            'course_id'     => $validated['course_id'],
            'module_id'     => $moduleId,
            'title'         => $validated['title'],
            'practice_word' => $validated['practice_word'], // 👈 Saved to DB
            'content'       => $validated['content'], 
            'video_url'     => $validated['video_url'],
            'order_index'   => 0,
            'is_published'  => true,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('lesson_resources', 'public');
                Attachment::create([
                    'lesson_id' => $lesson->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => Storage::url($path),
                    'file_type' => $file->getClientOriginalExtension(),
                ]);
            }
        }

        return response()->json($lesson->load('attachments'), 201);
    }

    /**
     * Mark a lesson as started
     */
    public function start(Request $request, $id)
    {
        $user = $request->user();

        $progress = ProgressRecord::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $id],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        return response()->json(['message' => 'Lesson started', 'progress' => $progress]);
    }

    /**
     * Mark a lesson/quiz as completed and award points/coins (GAM-01)
     */
    public function complete(Request $request, $id)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'score'  => 'required|numeric|min:0|max:100',
            'points' => 'nullable|integer',
        ]);

        $profile = $user->studentProfile()->firstOrCreate(
            ['user_id' => $user->id],
            ['total_points' => 0, 'total_coins' => 0]
        );

        $pointsAwarded = $validated['points'] ?? 50;
        $passed = $validated['score'] >= 70;

        $profile->increment('total_points', $pointsAwarded);
        
        if ($passed) {
            $profile->increment('total_coins', $pointsAwarded);
        }

        $progress = ProgressRecord::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $id],
            [
                'status'       => 'completed', 
                'score'        => $validated['score'],
                'completed_at' => now()
            ]
        );

        return response()->json([
            'message'      => $passed ? 'O ku ise! (Well done!)' : 'Keep trying!',
            'score'        => $progress->score,
            'total_points' => $profile->total_points,
            'total_coins'  => $profile->total_coins,
            'passed'       => $passed
        ]);
    }

    /**
     * Admin: Dedicated File Upload Endpoint
     */
    public function uploadContent(Request $request, $id)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Admin only.'], 403);
        }

        $lesson = Lesson::findOrFail($id);

        $validated = $request->validate([
            'file' => 'required|file|mimes:mp4,pdf,ppt,pptx,mp3,wav,jpg,png|max:102400', 
            'content_type' => 'required|in:video,document,audio,image',
        ]);

        $fileService = new FileUploadService();
        $url = '';
        
        switch ($validated['content_type']) {
            case 'video': $url = $fileService->uploadVideo($validated['file']); break;
            case 'document': $url = $fileService->uploadDocument($validated['file']); break;
            case 'audio': $url = $fileService->uploadAudio($validated['file']); break;
            case 'image': $url = $fileService->uploadImage($validated['file']); break;
        }

        $content = LessonContent::create([
            'lesson_id' => $lesson->id,
            'content_type' => $validated['content_type'],
            'file_url' => $url,
        ]);

        return response()->json(['message' => 'File uploaded', 'url' => $url, 'data' => $content], 201);
    }

/**
 * Admin: Update an existing lesson
 */
public function update(Request $request, $id)
{
    if (!$request->user()->is_admin) {
        return response()->json(['message' => 'Admin only.'], 403);
    }

    $lesson = Lesson::findOrFail($id);

    $validated = $request->validate([
        'title'         => 'required|string|max:255',
        'practice_word' => 'nullable|string|max:255',
        'content'       => 'required|string',
        'video_url'     => 'nullable|url',
    ]);

    $lesson->update($validated);

    return response()->json([
        'message' => 'Lesson updated successfully!',
        'lesson'  => $lesson
    ]);
}

/**
 * Admin: Delete a lesson and its media
 */
public function destroy(Request $request, $id)
{
    if (!$request->user()->is_admin) {
        return response()->json(['message' => 'Admin only.'], 403);
    }

    $lesson = Lesson::with('contents')->findOrFail($id);

    // 🧹 Clean up Physical Files from Storage
    foreach ($lesson->contents as $content) {
        // Assuming file_url is like '/storage/lesson_media/file.mp4'
        $path = str_replace('/storage/', '', $content->file_url);
        Storage::disk('public')->delete($path);
    }

    $lesson->delete();

    return response()->json(['message' => 'Lesson and associated media deleted.']);
}
}
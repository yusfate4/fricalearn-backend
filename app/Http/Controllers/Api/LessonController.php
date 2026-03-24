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
     * Admin: List all lessons for management (LMS-09)
     */
    public function index()
    {
        return response()->json(Lesson::with('module.course')->latest()->get());
    }

    /**
     * Fetch a specific lesson, its questions, and attachments (LMS-07)
     * Includes the new Video Explanation fields for the Quiz
     */
    public function show($id)
    {
        // Eager load everything needed for the lesson and the "Tutor Fail-Safe" quiz
        $lesson = Lesson::with(['questions', 'attachments', 'contents'])->find($id);

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        return response()->json($lesson);
    }

    /**
     * Admin: Create a new lesson with basic details (LMS-02, LMS-10)
     */
    public function store(Request $request)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Oda! Admin only.'], 403);
        }

        $validated = $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'title'       => 'required|string|max:255',
            'content'     => 'required|string', 
            'video_url'   => 'nullable|url',
            'files.*'     => 'file|max:20480', 
        ]);

        $moduleId = $request->module_id;
        if (!$moduleId) {
            $defaultModule = \App\Models\Module::firstOrCreate(
                ['course_id' => $validated['course_id'], 'title' => 'General Lessons'],
                ['order_index' => 1]
            );
            $moduleId = $defaultModule->id;
        }

        $lesson = Lesson::create([
            'course_id'    => $validated['course_id'],
            'module_id'    => $moduleId,
            'title'        => $validated['title'],
            'content'      => $validated['content'], 
            'video_url'    => $validated['video_url'],
            'order_index'  => 0,
            'is_published' => true,
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
     * NEW: Dedicated File Upload Endpoint (Videos, PDFs, Audio)
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
            case 'video':
                $url = $fileService->uploadVideo($validated['file']);
                break;
            case 'document':
                $url = $fileService->uploadDocument($validated['file']);
                break;
            case 'audio':
                $url = $fileService->uploadAudio($validated['file']);
                break;
            case 'image':
                $url = $fileService->uploadImage($validated['file']);
                break;
        }

        $content = LessonContent::create([
            'lesson_id' => $lesson->id,
            'content_type' => $validated['content_type'],
            'file_url' => $url,
        ]);

        return response()->json([
            'message' => 'File uploaded successfully',
            'url' => $url,
            'data' => $content
        ], 201);
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
     * Mark a lesson/quiz as completed and award points/coins (GAM-01)
     * Handles dynamic scoring from StudentQuizView
     */
    public function complete(Request $request, $id)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'score'  => 'nullable|numeric|min:0|max:100',
            'points' => 'nullable|integer',
        ]);

        // Ensure the student profile exists to hold points and coins
        $profile = $user->studentProfile;
        if (!$profile) {
            $profile = StudentProfile::create([
                'user_id' => $user->id,
                'total_points' => 0,
                'total_coins' => 0
            ]);
        }

        // Award points based on quiz results or default to 50
        $pointsAwarded = $validated['points'] ?? 50;
        $profile->increment('total_points', $pointsAwarded);
        
        // Award FricaCoins if the student passed (score >= 70%)
        if (($validated['score'] ?? 0) >= 70) {
            $profile->increment('total_coins', $pointsAwarded);
        }

        // Permanently record completion progress
        ProgressRecord::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $id],
            [
                'status' => 'completed', 
                'score' => $validated['score'] ?? 100,
                'completed_at' => now()
            ]
        );

        return response()->json([
            'message' => 'O ku ise! (Well done!)',
            'total_points' => $profile->total_points,
            'total_coins'  => $profile->total_coins,
            'added_points' => $pointsAwarded
        ]);
    }
}
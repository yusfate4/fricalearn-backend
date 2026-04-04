<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\ProgressRecord;
use App\Models\Attachment;
use App\Models\LessonContent;
use App\Models\StudentProfile;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
// 🚀 Use the pure Cloudinary SDK to bypass Service Provider errors
use Cloudinary\Cloudinary;

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
     * Fetch a specific lesson and its questions
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
     * 🚀 START LESSON: Mark as in_progress
     */
    public function start(Request $request, $id)
    {
        $user = $request->user();
        
        // 🛡️ Use Child ID if Parent is impersonating
        $targetUserId = $request->header('X-Active-Student-Id') ?: $user->id;

        $progress = ProgressRecord::updateOrCreate(
            ['user_id' => $targetUserId, 'lesson_id' => $id],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        return response()->json(['message' => 'Lesson started', 'progress' => $progress]);
    }

    /**
     * 🚀 COMPLETE LESSON: Award XP and Coins to the Student
     */
    public function complete(Request $request, $id)
    {
        $user = $request->user();
        
        // 🛡️ Ensure coins go to the Child, not the Parent
        $targetUserId = $request->header('X-Active-Student-Id') ?: $user->id;

        $validated = $request->validate([
            'score'  => 'required|numeric|min:0|max:100',
            'points' => 'nullable|integer',
        ]);

        $profile = StudentProfile::firstOrCreate(
            ['user_id' => $targetUserId],
            ['total_points' => 0, 'total_coins' => 0]
        );

        $pointsAwarded = $validated['points'] ?? 50;
        $passed = $validated['score'] >= 70;

        $profile->increment('total_points', $pointsAwarded);
        
        if ($passed) {
            $profile->increment('total_coins', $pointsAwarded);
        }

        $progress = ProgressRecord::updateOrCreate(
            ['user_id' => $targetUserId, 'lesson_id' => $id],
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
     * Admin: Create a new lesson
     */
    public function store(Request $request)
    {
        if (!$request->user()->role === 'admin' && !Number($request->user()->is_admin) === 1) {
            return response()->json(['message' => 'Oda! Admin only.'], 403);
        }

        $validated = $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'title'         => 'required|string|max:255',
            'practice_word' => 'nullable|string|max:255',
            'content'       => 'required|string', 
            'video_url'     => 'nullable|url',
            'files.*'       => 'file|max:20480', 
        ]);

        $moduleId = $request->module_id;
        if (!$moduleId) {
            $defaultModule = Module::firstOrCreate(
                ['course_id' => $validated['course_id'], 'title' => 'General Lessons'],
                ['order_index' => 1]
            );
            $moduleId = $defaultModule->id;
        }

        $lesson = Lesson::create([
            'course_id'     => $validated['course_id'],
            'module_id'     => $moduleId,
            'title'         => $validated['title'],
            'practice_word' => $validated['practice_word'],
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
     * 🏗️ UPLOAD LESSON CONTENT (Cloudinary Fixed Method)
     */
    public function uploadContent(Request $request, $id)
    {
        $isAdmin = auth()->user()->role === 'admin' || Number(auth()->user()->is_admin) === 1;
        if (!$isAdmin) {
            return response()->json(['message' => 'Admin only.'], 403);
        }

        $lesson = Lesson::findOrFail($id);
        
        $validated = $request->validate([
            'file' => 'required|file|max:102400', // 100MB Limit
            'content_type' => 'required|in:video,document,audio,image',
        ]);

        // 🚀 Manual SDK Setup to bypass the "null" ServiceProvider error
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        try {
            $file = $request->file('file');
            
            // Set resource_type based on the content being uploaded
            $resourceType = ($validated['content_type'] === 'video' || $validated['content_type'] === 'audio') 
                ? 'video' 
                : 'auto';

            $upload = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'fricalearn/lessons',
                    'resource_type' => $resourceType
                ]
            );

            $content = LessonContent::create([
                'lesson_id' => $lesson->id,
                'content_type' => $validated['content_type'],
                'file_url' => $upload['secure_url'],
            ]);

            return response()->json([
                'message' => 'Material uploaded successfully!',
                'url' => $upload['secure_url'],
                'data' => $content
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Cloudinary Upload Failed', 
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'practice_word' => 'nullable|string|max:255',
            'content'       => 'required|string',
            'video_url'     => 'nullable|url',
        ]);
        $lesson->update($validated);
        return response()->json(['message' => 'Lesson updated!', 'lesson' => $lesson]);
    }

    public function destroy(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->delete();
        return response()->json(['message' => 'Lesson deleted.']);
    }

    public function getCourseLessons($courseId)
    {
        return Lesson::where('course_id', $courseId)->get();
    }
}
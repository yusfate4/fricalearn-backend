<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\ProgressRecord;
use App\Models\Attachment;
use App\Models\LessonContent;
use App\Models\StudentProfile;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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
     * 🚀 START LESSON
     */
    public function start(Request $request, $id)
    {
        $user = $request->user();
        $targetUserId = $request->header('X-Active-Student-Id') ?: $user->id;

        $progress = ProgressRecord::updateOrCreate(
            ['user_id' => $targetUserId, 'lesson_id' => $id],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        return response()->json(['message' => 'Lesson started', 'progress' => $progress]);
    }

    /**
     * 🚀 COMPLETE LESSON: Award XP, Record Completion, and Notify Parent
     */
    public function complete(Request $request, $id)
{
    $user = $request->user();
    $targetUserId = $request->header('X-Active-Student-Id') ?: $user->id;

    $validated = $request->validate([
        'score'  => 'required|numeric|min:0|max:100',
        'points' => 'nullable|integer',
    ]);

    $lesson = Lesson::findOrFail($id); // Get lesson title for the description

    $profile = StudentProfile::firstOrCreate(
        ['user_id' => $targetUserId],
        ['total_points' => 0, 'total_coins' => 0]
    );

    $pointsAwarded = $validated['points'] ?? 50;
    $passed = $validated['score'] >= 70;

    // 🏆 1. Update Profile (Lifetime Total)
    $profile->increment('total_points', $pointsAwarded);
    if ($passed) {
        $profile->increment('total_coins', $pointsAwarded);
    }

    // 💰 1.5. THE FIX: Record Transaction (For Weekly Digest Summing)
    DB::table('gamification_transactions')->insert([
        'user_id'     => $targetUserId,
        'points'      => $pointsAwarded,
        'type'        => 'earn',
        'description' => 'Completed Lesson: ' . $lesson->title,
        'created_at'  => now(),
        'updated_at'  => now()
    ]);

    // 📊 2. Update Progress Record (General Status)
    $progress = ProgressRecord::updateOrCreate(
        ['user_id' => $targetUserId, 'lesson_id' => $id],
        [
            'status'       => 'completed', 
            'score'        => $validated['score'],
            'completed_at' => now()
        ]
    );

    // 🔏 3. Record in lesson_completions for the Weekly Digest Count
    DB::table('lesson_completions')->updateOrInsert(
        ['user_id' => $targetUserId, 'lesson_id' => $id],
        ['created_at' => now(), 'updated_at' => now()]
    );

    // 🔔 4. Notify Parent (Milestone)
    $student = User::find($targetUserId);
    if ($student && $student->parent_id) {
        $parent = User::find($student->parent_id);
        if ($parent) {
            // Optional: Send instant notification here if desired
        }
    }

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
        $isAdmin = auth()->user()->role === 'admin' || (int)auth()->user()->is_admin === 1;
        if (!$isAdmin) {
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
     * 🏗️ UPLOAD CONTENT
     */
    public function uploadContent(Request $request, $id)
    {
        $isAdmin = auth()->user()->role === 'admin' || (int)auth()->user()->is_admin === 1;
        if (!$isAdmin) {
            return response()->json(['message' => 'Admin only.'], 403);
        }

        $lesson = Lesson::findOrFail($id);
        
        $validated = $request->validate([
            'file' => 'required|file|max:102400', 
            'content_type' => 'required|in:video,document,audio,image',
        ]);

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        try {
            $file = $request->file('file');
            $resourceType = 'auto';
            if ($validated['content_type'] === 'video' || $validated['content_type'] === 'audio') {
                $resourceType = 'video';
            } elseif ($validated['content_type'] === 'document') {
                $resourceType = 'raw'; 
            } else {
                $resourceType = 'image';
            }

            $upload = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'fricalearn/lessons',
                    'resource_type' => $resourceType,
                    'access_mode' => 'public',
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
            return response()->json(['error' => $e->getMessage()], 500);
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
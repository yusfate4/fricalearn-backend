<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalSubject;
use App\Models\User;
use App\Models\UserExternalLessonProgress;
use Illuminate\Http\Request;

class ExternalSubjectController extends Controller
{
    public function index(Request $request)
    {
        try {
            $userId = $request->input('student_id') ?: auth()->id();
            $user = User::findOrFail($userId);
            
            $subjects = $user->externalSubjects()
                            ->with(['topics.lessons' => function($query) {
                                $query->select('id', 'topic_id', 'title', 'duration_minutes', 'order_index');
                            }])
                            ->get();

            foreach ($subjects as $subj) {
                $total = 0; $done = 0;
                foreach ($subj->topics as $top) {
                    foreach ($top->lessons as $les) {
                        $total++;
                        $p = UserExternalLessonProgress::where('user_id', $userId)
                            ->where('lesson_id', $les->id)
                            ->first();
                        if ($p && $p->status === 'completed') {
                            $done++;
                        }
                    }
                }
                $subj->pivot->progress_percentage = $total > 0 ? round(($done / $total) * 100) : 0;
            }

            return response()->json(['success' => true, 'subjects' => $subjects]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch external subjects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $userId = $request->input('student_id') ?: auth()->id();
            
            $subject = ExternalSubject::with(['topics' => function($query) {
                $query->orderBy('order_index');
            }, 'topics.lessons' => function($query) {
                $query->orderBy('order_index');
            }])->findOrFail($id);

            $allLessonsCount = 0;
            $completedLessonsCount = 0;
            $previousLessonCompleted = true;

            foreach ($subject->topics as $topic) {
                foreach ($topic->lessons as $lesson) {
                    $allLessonsCount++;

                    $progress = UserExternalLessonProgress::where('user_id', $userId)
                        ->where('lesson_id', $lesson->id)
                        ->first();

                    $isCompleted = $progress && $progress->status === 'completed';

                    if ($isCompleted) {
                        $completedLessonsCount++;
                    }

                    $lesson->is_locked = !$previousLessonCompleted;
                    $previousLessonCompleted = $isCompleted;
                }
            }

            $subject->progress_percentage = $allLessonsCount > 0 
                ? round(($completedLessonsCount / $allLessonsCount) * 100) 
                : 0;

            return response()->json([
                'success' => true,
                'subject' => $subject
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subject',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
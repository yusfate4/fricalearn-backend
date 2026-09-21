<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalSubject;
use App\Models\User;
use Illuminate\Http\Request;

class ExternalSubjectController extends Controller
{
    public function index(Request $request)
    {
        try {
            $userId = $request->input('student_id') ?: auth()->id();
            $user = User::findOrFail($userId);
            
            $subjects = $user->externalSubjects()
                            ->with(['topics.lessons' => function($query) use ($userId) {
                                $query->select('id', 'topic_id', 'title', 'duration_minutes', 'order_index', 'quiz_data', 'description')
                                    ->with(['userProgress' => function($q) use ($userId) {
                                        $q->where('user_id', $userId)->select('user_id', 'lesson_id', 'status', 'quiz_score');
                                    }]);
                            }])
                            ->get();

            foreach ($subjects as $subj) {
                $total = 0; $done = 0;
                foreach ($subj->topics as $top) {
                    foreach ($top->lessons as $les) {
                        $total++;
                        if ($les->userProgress->where('status', 'completed')->count() > 0) {
                            $done++;
                        }
                    }
                }
                $subj->pivot->progress_percentage = $total > 0 ? round(($done / $total) * 100) : 0;
            }

            return response()->json(['success' => true, 'subjects' => $subjects]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch subjects', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $userId = $request->input('student_id') ?: auth()->id();
            
            $subject = ExternalSubject::with(['topics' => function($query) use ($userId) {
                $query->with(['lessons' => function($q) use ($userId) {
                    $q->with(['userProgress' => function($p) use ($userId) {
                        $p->where('user_id', $userId);
                    }]);
                }])->orderBy('order_index');
            }])->findOrFail($id);

            $allLessonsCount = 0;
            $completedLessonsCount = 0;
            $previousLessonCompleted = true;

            foreach ($subject->topics as $topic) {
                $sortedLessons = $topic->lessons->sortBy('order_index');
                foreach ($sortedLessons as $lesson) {
                    $allLessonsCount++;
                    $progress = $lesson->userProgress->first();
                    $isCompleted = $progress && $progress->status === 'completed';

                    if ($isCompleted) {
                        $completedLessonsCount++;
                    }

                    $lesson->is_locked = !$previousLessonCompleted;
                    $previousLessonCompleted = $isCompleted;
                }
                $topic->setRelation('lessons', $sortedLessons);
            }

            $subject->progress_percentage = $allLessonsCount > 0 ? round(($completedLessonsCount / $allLessonsCount) * 100) : 0;

            return response()->json(['success' => true, 'subject' => $subject]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch subject', 'error' => $e->getMessage()], 404);
        }
    }
}
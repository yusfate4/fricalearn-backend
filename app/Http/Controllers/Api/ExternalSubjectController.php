<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalSubject;
use App\Models\User;
use Illuminate\Http\Request;

class ExternalSubjectController extends Controller
{
    /**
     * Get all subjects user is enrolled in
     * Supports student_id parameter for parents viewing their children's subjects
     */
    public function index(Request $request)
    {
        try {
            // If student_id is provided (parent viewing), use that
            // Otherwise use authenticated user
            $userId = $request->input('student_id') ?: auth()->id();
            
            // Get the user (either the authenticated user or the specified student)
            $user = User::findOrFail($userId);
            
            $subjects = $user->externalSubjects()
                            ->with(['topics.lessons' => function($query) use ($userId) {
                                // For the index/list view: only load title+id, not full description
                                // (description is 10,000 chars per lesson — loading all would be very slow)
                                $query->where(function($q) {
                                        $q->whereNotNull('description')
                                          ->where('description', '!=', 'fetched')
                                          ->whereRaw('CHAR_LENGTH(description) > 50');
                                    })
                                    ->select('id', 'topic_id', 'title', 'duration_minutes', 'order_index', 'quiz_data')
                                    ->with(['userProgress' => function($q) use ($userId) {
                                        $q->where('user_id', $userId)->select('user_id', 'lesson_id', 'status', 'quiz_score');
                                    }]);
                            }])
                            ->get();

            // Recompute live progress_percentage for each subject
            foreach ($subjects as $subject) {
                try {
                    $totalLessons = DB::table('external_lessons as l')
                        ->join('external_topics as t', 't.id', '=', 'l.topic_id')
                        ->where('t.subject_id', $subject->id)
                        ->count();

                    if ($totalLessons > 0) {
                        $completedLessons = DB::table('user_external_lesson_progress as p')
                            ->join('external_lessons as l', 'l.id', '=', 'p.lesson_id')
                            ->join('external_topics as t', 't.id', '=', 'l.topic_id')
                            ->where('p.user_id', $userId)
                            ->where('t.subject_id', $subject->id)
                            ->where('p.status', 'completed')
                            ->count();

                        $pct = (int) round(($completedLessons / $totalLessons) * 100);

                        if ($subject->pivot) {
                            $subject->pivot->progress_percentage = $pct;
                        }

                        DB::table('user_external_subject_enrollments')
                            ->where('user_id', $userId)
                            ->where('external_subject_id', $subject->id)
                            ->update(['progress_percentage' => $pct, 'updated_at' => now()]);
                    }
                } catch (\Exception $e) {
                    // Don't let progress calculation crash the whole response
                    \Illuminate\Support\Facades\Log::warning('Progress recalc failed for subject ' . $subject->id . ': ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'subjects' => $subjects
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch external subjects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single subject with topics and lessons
     * Supports student_id parameter for parents viewing their children's subjects
     */
    public function show(Request $request, $id)
    {
        try {
            // If student_id is provided (parent viewing), use that
            // Otherwise use authenticated user
            $userId = $request->input('student_id') ?: auth()->id();
            
            $subject = ExternalSubject::with(['topics' => function($query) use ($userId) {
                $query->with(['lessons' => function($q) use ($userId) {
                    // Only hide lessons with truly blank content
                    // Show all lessons regardless of description content
                // The lesson viewer handles missing content gracefully
                $q->with(['userProgress' => function($p) use ($userId) {
                            $p->where('user_id', $userId);
                        }]);
                }])->orderBy('order_index');
            }])->findOrFail($id);

            // ── Compute is_locked for each lesson ──────────────────
            // A lesson is locked only if the PREVIOUS lesson in the same
            // topic has never been started (no progress record at all).
            // Students can read any lesson they've unlocked, but must pass
            // the quiz (score ≥ 70%) before the NEXT lesson unlocks.
            foreach ($subject->topics as $topic) {
                $prevCompleted = true; // first lesson is always unlocked
                foreach ($topic->lessons as $i => $lesson) {
                    if ($i === 0) {
                        $lesson->is_locked = false;
                    } else {
                        $prevLesson    = $topic->lessons[$i - 1];
                        $prevProgress  = $prevLesson->userProgress->first();
                        // Locked if previous lesson has never been opened
                        $lesson->is_locked = !$prevProgress;
                        // Locked for quiz (next level) if prev quiz score < 70
                        $lesson->prev_quiz_passed = $prevProgress && $prevProgress->quiz_score >= 70;
                    }
                }
            }

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
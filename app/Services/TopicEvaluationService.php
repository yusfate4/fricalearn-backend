<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Evaluates a student's performance on a topic once every
 * quiz-bearing lesson in that topic has been completed.
 *
 * Place in: app/Services/TopicEvaluationService.php
 */
class TopicEvaluationService
{
    /**
     * Call after each quiz submission.
     * Returns the evaluation array if the topic is now complete, otherwise null.
     */
    public function evaluateIfTopicComplete(int $studentId, int $topicId): ?array
    {
        // Lessons in this topic that HAVE a quiz (lessons without quizzes
        // can't reach "completed" status, so they don't block completion)
        $quizLessons = DB::table('external_lessons')
            ->where('topic_id', $topicId)
            ->whereNotNull('quiz_data')
            ->pluck('id');

        if ($quizLessons->isEmpty()) return null;

        $completedCount = DB::table('user_external_lesson_progress')
            ->where('user_id', $studentId)
            ->whereIn('lesson_id', $quizLessons)
            ->where('status', 'completed')
            ->count();

        if ($completedCount < $quizLessons->count()) return null; // topic not finished yet

        // ── Topic complete — compute the evaluation ────────────────
        // Best score per lesson (students may retry)
        $bestScores = DB::table('quiz_performance')
            ->where('student_id', $studentId)
            ->whereIn('lesson_id', $quizLessons)
            ->select('lesson_id', DB::raw('MAX(score) as best_score'))
            ->groupBy('lesson_id')
            ->get();

        if ($bestScores->isEmpty()) return null;

        $average = (int) round($bestScores->avg('best_score'));

        // Weak lessons = best score below 70%
        $weakLessonIds = $bestScores->where('best_score', '<', 70)->pluck('lesson_id');
        $weakLessons   = DB::table('external_lessons')
            ->whereIn('id', $weakLessonIds)
            ->pluck('title')
            ->toArray();

        $gradeLabel = match (true) {
            $average >= 90 => 'Excellent',
            $average >= 80 => 'Very Good',
            $average >= 70 => 'Good',
            $average >= 60 => 'Fair',
            default        => 'Needs Practice',
        };

        $subjectId = DB::table('external_topics')->where('id', $topicId)->value('subject_id');

        DB::table('topic_evaluations')->updateOrInsert(
            ['student_id' => $studentId, 'topic_id' => $topicId],
            [
                'subject_id'        => $subjectId,
                'average_score'     => $average,
                'lessons_completed' => $completedCount,
                'total_lessons'     => $quizLessons->count(),
                'grade_label'       => $gradeLabel,
                'weak_lessons'      => json_encode($weakLessons),
                'completed_at'      => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $topicTitle = DB::table('external_topics')->where('id', $topicId)->value('title');

        return [
            'topic_title'       => $topicTitle,
            'average_score'     => $average,
            'grade_label'       => $gradeLabel,
            'lessons_completed' => $completedCount,
            'total_lessons'     => $quizLessons->count(),
            'weak_lessons'      => $weakLessons,
        ];
    }
}

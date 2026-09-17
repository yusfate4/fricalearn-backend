<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExternalSubject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalSubjectController extends Controller
{
    /**
     * GET /external/subjects
     * Fast: only loads subject + topic count. No lessons.
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->input('student_id') ?: auth()->id();
            $user   = User::findOrFail($userId);

            $subjects = $user->externalSubjects()
                ->withCount('topics')
                ->get()
                ->map(function ($subject) {
                    return [
                        'id'                  => $subject->id,
                        'name'                => $subject->name,
                        'key_stage'           => $subject->key_stage,
                        'year_group'          => $subject->year_group,
                        'source'              => $subject->source,
                        'curriculum_region'   => $subject->curriculum_region,
                        'framework_code'      => $subject->framework_code,
                        'topics_count'        => $subject->topics_count,
                        'pivot'               => [
                            'enrolled_at'         => $subject->pivot->enrolled_at,
                            'progress_percentage' => $subject->pivot->progress_percentage ?? 0,
                        ],
                    ];
                });

            return response()->json(['success' => true, 'subjects' => $subjects]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /external/subjects/{id}
     * Loads topics + lesson titles only (no descriptions/transcripts).
     */
    public function show(Request $request, $id)
    {
        try {
            $userId = $request->input('student_id') ?: auth()->id();

            $subject = ExternalSubject::with(['topics' => function ($query) use ($userId) {
                $query
                    ->orderBy('order_index')
                    ->with(['lessons' => function ($q) use ($userId) {
                        $q->select(
                                'id', 'topic_id', 'title',
                                'order_index', 'duration_minutes',
                                // quiz_data needed to know if quiz exists (null check only)
                                DB::raw('CASE WHEN quiz_data IS NOT NULL THEN 1 ELSE 0 END as has_quiz')
                            )
                            ->orderBy('order_index')
                            ->with(['userProgress' => function ($p) use ($userId) {
                                $p->where('user_id', $userId)
                                  ->select('lesson_id', 'status', 'quiz_score');
                            }]);
                    }]);
            }])->findOrFail($id);

            return response()->json(['success' => true, 'subject' => $subject]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

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
                                $query->with(['userProgress' => function($q) use ($userId) {
                                    $q->where('user_id', $userId);
                                }]);
                            }])
                            ->get();

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
                    $q->with(['userProgress' => function($p) use ($userId) {
                        $p->where('user_id', $userId);
                    }]);
                }])->orderBy('order_index');
            }])->findOrFail($id);

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
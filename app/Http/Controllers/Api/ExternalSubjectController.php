<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExternalSubject;
use Illuminate\Http\Request;

class ExternalSubjectController extends Controller
{
    /**
     * Get all subjects user is enrolled in
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $subjects = $user->externalSubjects()
                        ->with(['topics.lessons' => function($query) use ($user) {
                            $query->with(['userProgress' => function($q) use ($user) {
                                $q->where('user_id', $user->id);
                            }]);
                        }])
                        ->get();

        return response()->json([
            'success' => true,
            'subjects' => $subjects
        ]);
    }

    /**
     * Get single subject with topics and lessons
     */
    public function show($id)
    {
        $user = auth()->user();
        
        $subject = ExternalSubject::with(['topics' => function($query) use ($user) {
            $query->with(['lessons' => function($q) use ($user) {
                $q->with(['userProgress' => function($p) use ($user) {
                    $p->where('user_id', $user->id);
                }]);
            }])->orderBy('order_index');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'subject' => $subject
        ]);
    }
}
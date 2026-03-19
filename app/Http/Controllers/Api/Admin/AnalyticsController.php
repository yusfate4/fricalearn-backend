<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    //
    public function index() {
    return response()->json([
        'avg_score' => 88, // In future, calculate from QuizAttempt::avg('score')
        'active_count' => \App\Models\User::where('is_admin', 0)->count(),
        'completed_count' => 15,
        'recent_students' => \App\Models\User::where('is_admin', 0)
            ->with('studentProfile')
            ->take(5)
            ->get()
    ]);
}
}


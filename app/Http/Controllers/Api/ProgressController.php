<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentAnalytics;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function analytics(Request $request)
    {
        $studentId = $request->user()->id;
        
        $stats = StudentAnalytics::where('student_id', $studentId)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json($stats);
    }
}
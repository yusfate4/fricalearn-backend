<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveClass;
use App\Services\LiveClassService;
use Illuminate\Http\Request;

class LiveClassController extends Controller
{
    protected $liveClassService;

    public function __construct(LiveClassService $lcs)
    {
        $this->liveClassService = $lcs;
    }

    public function index(Request $request)
    {
        // List upcoming classes for the student's enrolled courses
        $classes = LiveClass::with(['tutor', 'lesson'])
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json($classes);
    }

    public function store(Request $request)
    {
        // Only Tutors or Admins should be able to schedule classes
        if ($request->user()->role === 'student') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $class = $this->liveClassService->createLiveClass($request->all());
        return response()->json($class, 201);
    }
}
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
    // 🚀 FIX: Show classes starting in the future OR that started in the last 2 hours
    $classes = LiveClass::with(['tutor', 'lesson'])
        ->where('scheduled_at', '>', now()->subHours(2)) 
        ->orderBy('scheduled_at', 'asc')
        ->get();

    return response()->json($classes);
}

public function show($id)
{
    // Fetch the class with tutor and lesson details
    $liveClass = LiveClass::with(['tutor', 'lesson'])->findOrFail($id);

    return response()->json($liveClass);
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

    public function destroy($id)
{
    $liveClass = LiveClass::findOrFail($id);
    $liveClass->delete();

    return response()->json(['message' => 'Class deleted successfully']);
}
}
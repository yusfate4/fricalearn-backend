<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveClass;
use App\Services\LiveClassService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiveClassController extends Controller
{
    protected $liveClassService;

    public function __construct(LiveClassService $lcs)
    {
        $this->liveClassService = $lcs;
    }

    /**
     * 📺 1. LIST LIVE CLASSES
     * Used by both the Student view and the Admin Manager
     */
    public function index(Request $request)
    {
        try {
            // Fetch classes starting in the future OR that started in the last 2 hours
            // 💡 Note: Ensure your LiveClass model has 'tutor' and 'lesson' relationships defined
            $classes = LiveClass::with(['tutor', 'lesson'])
                ->where('scheduled_at', '>', now()->subHours(2)) 
                ->orderBy('scheduled_at', 'asc')
                ->get();

            return response()->json($classes);
        } catch (\Exception $e) {
            Log::error("LiveClass Index Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to load classes'], 500);
        }
    }

    /**
     * 📊 2. ADMIN DATA (The "404" Fix)
     * If your frontend calls /api/admin/live-classes/admin-data, this handles it.
     */
    public function adminData()
    {
        return response()->json([
            'total_classes' => LiveClass::count(),
            'upcoming_count' => LiveClass::where('scheduled_at', '>', now())->count(),
            'recent_classes' => LiveClass::latest()->take(5)->get()
        ]);
    }

    /**
     * 🔍 3. SHOW SINGLE CLASS
     */
    public function show($id)
    {
        $liveClass = LiveClass::with(['tutor', 'lesson'])->findOrFail($id);
        return response()->json($liveClass);
    }

    /**
     * 🚀 4. STORE NEW CLASS
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        // Use the isStaff helper or role check
        $isStaff = $user->role === 'admin' || $user->role === 'tutor' || (int)$user->is_admin === 1;

        if (!$isStaff) {
            return response()->json(['message' => 'Unauthorized. Staff only.'], 403);
        }

        try {
            $class = $this->liveClassService->createLiveClass($request->all());
            return response()->json($class, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * 🗑️ 5. DELETE CLASS
     */
    public function destroy($id)
    {
        $liveClass = LiveClass::findOrFail($id);
        $liveClass->delete();

        return response()->json(['message' => 'Class deleted successfully']);
    }
}
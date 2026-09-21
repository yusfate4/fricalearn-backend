<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveClassController extends Controller
{
    public function index()
    {
        $classes = LiveClass::with(['tutor:id,name'])
            ->where('scheduled_at', '>', now()->subHours(2))
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json($classes);
    }

    public function show($id)
    {
        return response()->json(LiveClass::with(['tutor:id,name'])->findOrFail($id));
    }

    public function adminData()
    {
        $upcoming = LiveClass::with(['tutor:id,name'])
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->get();

        $past = LiveClass::with(['tutor:id,name'])
            ->where('scheduled_at', '<=', now())
            ->orderByDesc('scheduled_at')
            ->limit(10)
            ->get();

        return response()->json([
            'upcoming'       => $upcoming,
            'past'           => $past,
            'upcoming_count' => $upcoming->count(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isStaff = $user->role === 'admin' || $user->role === 'tutor' || (int)$user->is_admin === 1;

        if (!$isStaff) {
            return response()->json(['message' => 'Unauthorized. Staff only.'], 403);
        }

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'meeting_url'      => 'nullable|string',
            'is_paid'          => 'nullable|boolean',
            'price'            => 'nullable|numeric',
            'max_attendees'    => 'nullable|integer',
            'status'           => 'nullable|string',
        ]);

        // lesson_id is required by DB but conceptually optional for standalone live classes
        // Use 0 as a sentinel value (or the first lesson if available)
        $lessonId = DB::table('lessons')->value('id') ?? 1;
        $tutorId  = $user->id;

        $class = LiveClass::create([
            'lesson_id'        => $lessonId,
            'tutor_id'         => $tutorId,
            'title'            => $validated['title'],
            'description'      => $validated['description'] ?? null,
            'scheduled_at'     => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'meeting_url'      => $validated['meeting_url'] ?? null,
            'status'           => $validated['status'] ?? 'scheduled',
            'max_attendees'    => $validated['max_attendees'] ?? 50,
        ]);

        return response()->json($class, 201);
    }

    public function destroy($id)
    {
        $class = LiveClass::findOrFail($id);
        $class->delete();
        return response()->json(['message' => 'Class deleted successfully']);
    }
}

<?php

namespace App\Services;

use App\Models\LiveClass;
use Illuminate\Support\Facades\Auth;

class LiveClassService
{
    /**
     * Create a new Live Class record.
     * In Phase 2, this is where you'd call the Zoom/Meet API.
     */
    public function createLiveClass(array $data)
    {
        return LiveClass::create([
            'lesson_id'       => $data['lesson_id'],
            'tutor_id'        => Auth::id(), // The logged-in Admin/Tutor
            'title'           => $data['title'],
            'scheduled_at'    => $data['scheduled_at'],
            'duration_minutes'=> $data['duration_minutes'] ?? 45,
            'meeting_url'     => $data['meeting_url'] ?? null,
            'status'          => 'scheduled',
            'max_attendees'   => $data['max_attendees'] ?? 20,
        ]);
    }
}
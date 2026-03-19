<?php

namespace App\Services;

use App\Models\LiveClass;
use App\Models\ClassAttendance;

class LiveClassService
{
    /**
     * Create a new scheduled live class.
     */
    public function createLiveClass(array $data): LiveClass
    {
        return LiveClass::create([
            'lesson_id' => $data['lesson_id'],
            'tutor_id' => $data['tutor_id'],
            'title' => $data['title'],
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'],
            'meeting_id' => 'frica_' . uniqid(), // Unique ID for the video room
            'status' => 'scheduled',
        ]);
    }
}
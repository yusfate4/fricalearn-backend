<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'tutor_id',
        'title',
        'scheduled_at',
        'duration_minutes',
        'meeting_url',
        'meeting_id',
        'recording_url',
        'status',
        'max_attendees'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    // 🔗 Relationship: The Tutor (User) hosting the class
    public function tutor()
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    // 🔗 Relationship: The specific Lesson this class is for
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserExternalLessonProgress extends Model
{
    protected $table = 'user_external_lesson_progress';
    
    protected $fillable = [
        'user_id',
        'lesson_id',
        'status',
        'video_watched',
        'quiz_score',
        'quiz_attempts',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'video_watched' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(ExternalLesson::class);
    }
}
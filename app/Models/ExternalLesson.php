<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalLesson extends Model
{
    protected $fillable = [
        'topic_id',
        'title',
        'description',
        'video_url',
        'slide_url',
        'worksheet_url',
        'quiz_data',
        'duration_minutes',
        'order_index',
        'external_id'
    ];

    protected $casts = [
        'quiz_data' => 'array'
    ];

    public function topic()
    {
        return $this->belongsTo(ExternalTopic::class, 'topic_id');
    }

    public function userProgress()
    {
        return $this->hasMany(UserExternalLessonProgress::class, 'lesson_id');
    }

    public function progressForUser($userId)
    {
        return $this->userProgress()->where('user_id', $userId)->first();
    }
}
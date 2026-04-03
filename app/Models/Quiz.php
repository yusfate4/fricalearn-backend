<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    // 🚀 Allow these fields to be filled
    protected $fillable = [
        'lesson_id', 
        'title', 
        'description', 
        'passing_score'
    ];

    // Relationship: A quiz belongs to a lesson
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    // Relationship: A quiz has many attempts
    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
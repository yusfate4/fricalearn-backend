<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $fillable = [
        'student_id', 
        'quiz_id', 
        'score', 
        'passed', 
        'time_taken_seconds', 
        'completed_at'
    ];

    // 🔗 Relationship: An attempt belongs to one Quiz
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    // 🔗 Relationship: An attempt belongs to one Student
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
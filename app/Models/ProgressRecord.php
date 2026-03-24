<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressRecord extends Model
{
    // 🚨 Added 'score' to fillable so we can save Ayo's results!
    protected $fillable = [
        'user_id', 
        'lesson_id', 
        'status', 
        'score', // 👈 Essential for Analytics
        'started_at', 
        'completed_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 🔗 RELATIONSHIP: Every progress record belongs to one Lesson
     * This allows us to show the Lesson Title in the Analytics Timeline.
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    /**
     * 🔗 RELATIONSHIP: Every progress record belongs to one User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
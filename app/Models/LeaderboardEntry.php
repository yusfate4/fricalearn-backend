<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaderboardEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'period',
        'points',
        'rank',
        'period_start',
        'period_end',
    ];

    public function student()
    {
        // This links the leaderboard entry back to the student's profile
        return $this->belongsTo(StudentProfile::class, 'student_id', 'user_id');
    }
}
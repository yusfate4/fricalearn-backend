<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'question_text',
        'option_a',
        'option_b',
        'option_c',
        'correct_answer',
        'explanation_video_url', // 👈 Add this
        'explanation_text'       // 👈 Add this
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
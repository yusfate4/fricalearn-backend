<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'content_type',
        'file_url',
    ];

    // A piece of content belongs to one specific lesson
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
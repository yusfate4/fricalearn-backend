<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
       'course_id', 
    'module_id', 
    'title', 
    'practice_word',
    'content', 
    'video_url', 
    'order_index', 
    'is_published'
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'points_reward' => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    // Add this inside the Lesson class
public function attachments()
{
    return $this->hasMany(Attachment::class);
}

public function questions()
{
    return $this->hasMany(Question::class);
}

   public function contents()
    {
        return $this->hasMany(LessonContent::class);
    }


    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function liveClasses()
    {
        return $this->hasMany(LiveClass::class);
    }

    public function progressRecords()
    {
        return $this->hasMany(ProgressRecord::class);
    }

    // Get a specific student's progress for this lesson
    public function getProgressForStudent(int $studentId)
    {
        return $this->progressRecords()
            ->where('student_id', $studentId)
            ->first();
    }
}
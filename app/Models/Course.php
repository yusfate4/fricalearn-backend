<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

   protected $fillable = [
    'title',
    'category', // Added to match migration and LMS-01 
    'description',
    'subject',
    'level',
    'thumbnail_url',
    'is_published',
    'created_by',
];

// This helper will be vital for your Admin Stats 
public function getActiveStudentsCountAttribute()
{
    return $this->enrollments()->where('status', 'active')->count();
}

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('order_index');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'course_enrollments', 'course_id', 'student_id')
            ->withPivot('enrolled_at', 'completed_at', 'progress_percentage', 'status')
            ->withTimestamps();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeBySubject($query, string $subject)
    {
        return $query->where('subject', $subject);
    }

    // Calculate total lessons in course
    public function getTotalLessonsAttribute()
    {
        return $this->modules->sum(function ($module) {
            return $module->lessons->count();
        });
    }
}
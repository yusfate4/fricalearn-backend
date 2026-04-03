<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'description',
        'subject',
        'level',
        'thumbnail_url', // 🖼️ This is your DB column for the image path
        'is_published',
        'created_by',
    ];

    /**
     * 🚀 THE IMAGE FIX: THE ACCESSOR
     * This automatically creates a 'full_thumbnail_url' field in your JSON response.
     */
    protected $appends = ['full_thumbnail_url', 'active_students_count', 'total_lessons'];

  // Inside app/Models/Course.php

public function getFullThumbnailUrlAttribute()
{
    if (!$this->thumbnail_url) return null;

    // 🚀 THE FIX: If the string already starts with 'storage/', strip it first
    // This prevents the 'storage//storage/' error in your Network tab
    $cleanPath = str_replace('storage/', '', $this->thumbnail_url);
    $cleanPath = ltrim($cleanPath, '/'); // Remove any leading slashes

    return asset('storage/' . $cleanPath);
}

    // --- EXISTING LOGIC ---

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

    public function getTotalLessonsAttribute()
    {
        return $this->modules->sum(function ($module) {
            return $module->lessons ? $module->lessons->count() : 0;
        });
    }
}
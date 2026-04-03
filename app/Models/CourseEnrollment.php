<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    use HasFactory;
    // protected $table = 'enrollments';


    protected $table = 'course_enrollments';
    protected $fillable = [
        'course_id',
        'student_id',
        'status',
        'enrolled_at',
        'expires_at',
        'progress_percentage'
    ];

    // Optional: add relationships
    public function student() {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course() {
        return $this->belongsTo(Course::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
        'country',
        'timezone',
        'avatar_url',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function tutorProfile()
    {
        return $this->hasOne(TutorProfile::class);
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class, 'student_id');
    }

    public function progressRecords()
    {
        return $this->hasMany(ProgressRecord::class, 'student_id');
    }

    public function children()
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'parent_id', 'student_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'student_id', 'parent_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    // Scopes
    public function scopeStudents($query)
    {
        return $query->where('role', 'student');
    }

    public function scopeTutors($query)
    {
        return $query->where('role', 'tutor');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper methods
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isTutor(): bool
    {
        return $this->role === 'tutor';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }
}
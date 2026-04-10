<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
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

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'is_admin' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Student & Tutor Profiles
    |--------------------------------------------------------------------------
    */

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function tutorProfile()
    {
        return $this->hasOne(TutorProfile::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Parent & Child Linking Logic (The "Bridge")
    |--------------------------------------------------------------------------
    */

    /**
     * RELATIONSHIP: For the Parent to see their Children.
     * Uses the 'parent_child' pivot table.
     */
    public function children()
    {
        return $this->belongsToMany(
            User::class,
            'parent_child', // Pivot table name
            'parent_id',    // Foreign key for Parent
            'child_id'      // Foreign key for Child
        )
        ->withPivot('relationship')
        ->withTimestamps();
    }

    /**
     * RELATIONSHIP: For the Child to see their Parent(s).
     */
    public function parents()
    {
        return $this->belongsToMany(
            User::class,
            'parent_child',
            'child_id',
            'parent_id'
        )
        ->withPivot('relationship')
        ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Academic & Progress Relationships
    |--------------------------------------------------------------------------
    */

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class, 'student_id');
    }

    public function progressRecords()
    {
        return $this->hasMany(ProgressRecord::class, 'user_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class, 'student_id');
    }

    public function pointsHistory()
    {
        return $this->hasMany(PointsHistory::class, 'student_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return (bool)$this->is_admin || $this->role === 'admin';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    public function isTutor(): bool
    {
        return $this->role === 'tutor';
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Accessors (Custom Attributes)
    |--------------------------------------------------------------------------
    */

    /**
     * Returns a professional display name for Admin sidebars.
     * Example: "Dahud Yusuf (Parent of Sodiq)"
     */
    public function getAdminDisplayNameAttribute()
    {
        if ($this->role === 'parent') {
            $childrenNames = $this->children->pluck('name')->implode(', ');
            return $childrenNames 
                ? "{$this->name} (Parent of {$childrenNames})" 
                : "{$this->name} (Parent)";
        }
        
        return $this->name;
    }

    public function getEmailForPasswordReset()
{
    return $this->email;
}
}
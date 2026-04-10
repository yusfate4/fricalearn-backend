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
        'last_login_at',
        'email_verified_at', // 🚀 Added to fillable for easy manual updates
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
    | Verification Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine if the user has verified their email address.
     * Overridden to ensure boolean consistency across the app.
     */
    public function hasVerifiedEmail()
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Mark the given user's email as verified.
     */
    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

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
    | Parent & Child Linking Logic
    |--------------------------------------------------------------------------
    */

    public function children()
    {
        return $this->belongsToMany(
            User::class,
            'parent_child', 
            'parent_id',    
            'child_id'      
        )
        ->withPivot('relationship')
        ->withTimestamps();
    }

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
    | Accessors
    |--------------------------------------------------------------------------
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

    /**
     * Get the email address that should be used for password reset.
     */
    public function getEmailForPasswordReset()
    {
        return $this->email;
    }
}
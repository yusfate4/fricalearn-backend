<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'email_verified_at',
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

    /**
     * Automatically append these attributes to JSON responses.
     */
    protected $appends = ['admin_display_name'];

    /*
    |--------------------------------------------------------------------------
    | Verification Helpers
    |--------------------------------------------------------------------------
    */

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Staff & Student Profiles
    |--------------------------------------------------------------------------
    */

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function tutorProfile(): HasOne
    {
        return $this->hasOne(TutorProfile::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Parent & Child Linking Logic
    |--------------------------------------------------------------------------
    */

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_child', 'parent_id', 'child_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_child', 'child_id', 'parent_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Academic & Progress Relationships
    |--------------------------------------------------------------------------
    */

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'student_id');
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(ProgressRecord::class, 'user_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'student_id');
    }

    public function pointsHistory(): HasMany
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

    public function isTutor(): bool
    {
        return $this->role === 'tutor';
    }

    /**
     * Check if user is either an Admin or a Tutor (Staff access).
     */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isTutor();
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & Accessors
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

    public function getAdminDisplayNameAttribute(): string
    {
        if ($this->role === 'parent') {
            $childrenNames = $this->children->pluck('name')->implode(', ');
            return $childrenNames 
                ? "{$this->name} (Parent of {$childrenNames})" 
                : "{$this->name} (Parent)";
        }
        
        return $this->name;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }
}
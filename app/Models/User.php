<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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
        // 🌍 Dual Curriculum fields
        'curriculum_region',
        'payment_currency',
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
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'is_admin'          => 'boolean',
    ];

    /**
     * Automatically append these attributes to JSON responses.
     */
    protected $appends = ['admin_display_name', 'curriculum_display'];

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
    | External Subjects (Dual Curriculum)
    |--------------------------------------------------------------------------
    */

    /**
     * All external subjects this user is enrolled in.
     */
    public function externalSubjects(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalSubject::class,
            'user_external_subject_enrollments',
            'user_id',
            'external_subject_id'
        )->withPivot('enrolled_at', 'progress_percentage')
         ->withTimestamps();
    }

    /**
     * External subjects filtered to user's own curriculum region only.
     */
    public function curriculumSubjects(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalSubject::class,
            'user_external_subject_enrollments',
            'user_id',
            'external_subject_id'
        )->withPivot('enrolled_at', 'progress_percentage')
         ->withTimestamps()
         ->where(function ($query) {
             $query->where('external_subjects.curriculum_region', $this->curriculum_region)
                   ->orWhere('external_subjects.curriculum_region', 'both');
         });
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
    | Curriculum Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Is this user on the UK curriculum?
     */
    public function isUkCurriculum(): bool
    {
        return $this->curriculum_region === 'uk';
    }

    /**
     * Is this user on the Nigerian curriculum?
     */
    public function isNigerianCurriculum(): bool
    {
        return $this->curriculum_region === 'nigeria';
    }

    /**
     * Is this user paying in GBP?
     */
    public function isGbpPayer(): bool
    {
        return $this->payment_currency === 'GBP';
    }

    /**
     * Is this user paying in NGN?
     */
    public function isNgnPayer(): bool
    {
        return $this->payment_currency === 'NGN';
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

    public function scopeUkStudents($query)
    {
        return $query->where('curriculum_region', 'uk');
    }

    public function scopeNigerianStudents($query)
    {
        return $query->where('curriculum_region', 'nigeria');
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

    /**
     * Human-readable curriculum label for display in UI.
     */
    public function getCurriculumDisplayAttribute(): string
    {
        return match($this->curriculum_region) {
            'nigeria' => '🇳🇬 Nigerian Curriculum (NERDC)',
            'uk'      => '🇬🇧 UK National Curriculum (Oak)',
            default   => 'Not set',
        };
    }
}
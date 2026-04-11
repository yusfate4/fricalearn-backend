<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 
        'bio', 
        'specialization', 
        'qualification', 
        'social_links', 
        'is_verified'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'social_links' => 'array',
        'is_verified' => 'boolean',
    ];

    /**
     * Get the user that owns the tutor profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
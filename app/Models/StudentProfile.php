<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id', // 🚀 NEW: Link to the parent account
        'date_of_birth',
        'grade_level',
        'learning_language',
        'current_level',
        'total_points',
        'total_coins',
        'learning_goal',
    'starting_level',
    'onboarding_completed',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'total_points' => 'integer',
        'total_coins' => 'integer',
        'current_level' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: A Student Profile belongs to a Parent
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Relationship to the Badge model via the pivot table
     */
    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'student_badges')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    /**
     * Connects the current_level integer to the actual Level model data
     */
    public function level()
    {
        return $this->belongsTo(Level::class, 'current_level', 'level_number');
    }

    /*
    |--------------------------------------------------------------------------
    | Points & Coins Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Core method to award points. Returns true if the student leveled up
     */
    public function addPoints(int $points, string $reason, string $referenceType = null, int $referenceId = null): bool
    {
        $this->increment('total_points', $points);
        
        PointsHistory::create([
            'student_id' => $this->user_id,
            'points' => $points,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        // Check for level up immediately after adding points
        return $this->checkLevelUp();
    }

    public function addCoins(int $coins)
    {
        $this->increment('total_coins', $coins);
    }

    public function spendCoins(int $coins): bool
    {
        if ($this->total_coins >= $coins) {
            $this->decrement('total_coins', $coins);
            return true;
        }
        return false;
    }

    /**
     * Compares total points against the 'levels' table to see if a promotion is due
     */
    private function checkLevelUp(): bool
    {
        // Find the highest level where points_required is met
        $eligibleLevel = Level::where('points_required', '<=', $this->total_points)
            ->orderBy('level_number', 'desc')
            ->first();

        if ($eligibleLevel && $eligibleLevel->level_number > $this->current_level) {
            $this->update(['current_level' => $eligibleLevel->level_number]);
            return true; 
        }
        
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors (For Frontend Display)
    |--------------------------------------------------------------------------
    */

    /**
     * Returns a cultural title based on point milestones
     */
    public function getRankAttribute()
    {
        $points = $this->total_points;

        if ($points >= 2000) return 'Oga (Master)';
        if ($points >= 1500) return 'Akonni (Hero)';
        if ($points >= 1000) return 'Jagunjagun (Warrior)';
        if ($points >= 500)  return 'Akeko (Student)';
        
        return 'Omode (Child)';
    }

    /**
     * Calculates how much XP is needed to reach the next level threshold
     */
    public function getXpToNextLevelAttribute()
    {
        $nextLevel = Level::where('level_number', '>', $this->current_level)
            ->orderBy('level_number', 'asc')
            ->first();

        if (!$nextLevel) return 0;

        return max(0, $nextLevel->points_required - $this->total_points);
    }

    // Append these to the JSON output for the Dashboard
    protected $appends = ['rank', 'xp_to_next_level'];
}
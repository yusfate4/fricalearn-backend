<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'grade_level',
        'learning_language',
        'current_level',
        'total_points',
        'total_coins',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'total_points' => 'integer',
        'total_coins' => 'integer',
        'current_level' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'student_badges')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function level()
    {
        return $this->belongsTo(Level::class, 'current_level', 'level_number');
    }

    // Add points to student
    public function addPoints(int $points, string $reason, string $referenceType = null, int $referenceId = null)
    {
        $this->increment('total_points', $points);
        
        PointsHistory::create([
            'student_id' => $this->user_id,
            'points' => $points,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        // Check for level up
        $this->checkLevelUp();
    }

    // Add coins to student
    public function addCoins(int $coins)
    {
        $this->increment('total_coins', $coins);
    }

    // Spend coins
    public function spendCoins(int $coins): bool
    {
        if ($this->total_coins >= $coins) {
            $this->decrement('total_coins', $coins);
            return true;
        }
        return false;
    }

    // Check and apply level up
    private function checkLevelUp()
    {
        $nextLevel = Level::where('points_required', '<=', $this->total_points)
            ->orderBy('level_number', 'desc')
            ->first();

        if ($nextLevel && $nextLevel->level_number > $this->current_level) {
            $this->update(['current_level' => $nextLevel->level_number]);
            
            // Note: We will create this Event later
            // event(new \App\Events\StudentLeveledUp($this, $nextLevel));
        }
    }

    public function getRankAttribute()
{
    $points = $this->total_points;

    if ($points >= 2000) return 'Oga (Master)';
    if ($points >= 1500) return 'Akonni (Hero)';
    if ($points >= 1000) return 'Jagunjagun (Warrior)';
    if ($points >= 500)  return 'Akeko (Student)';
    
    return 'Omode (Child)';
}

// Add 'rank' to the JSON output
protected $appends = ['rank'];
}
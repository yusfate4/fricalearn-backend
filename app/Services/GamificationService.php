<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\PointsHistory;
use App\Models\LeaderboardEntry;
use App\Models\Badge;
use App\Models\Notification;
use Carbon\Carbon;

class GamificationService
{
    /**
     * Award points to a student and check for Level Ups or Badges.
     * Returns true if a Level Up occurred.
     */
    public function awardPoints(int $studentId, int $points, string $reason, string $refType = null, int $refId = null): bool 
    {
        $profile = StudentProfile::where('user_id', $studentId)->first();
        $leveledUp = false;
        
        if ($profile) {
            // 1. Add points and check for Level Up
            $leveledUp = $profile->addPoints($points, $reason, $refType, $refId);
            
            // 2. Sync the leaderboard
            $this->updateLeaderboard($studentId);

            // 3. Check if any new badges were earned
            $this->checkBadgeEligibility($studentId);
        }

        return $leveledUp;
    }

    /**
     * Scans unearned badges to see if the student now meets criteria.
     */
    public function checkBadgeEligibility(int $studentId): void
    {
        $profile = StudentProfile::where('user_id', $studentId)->first();
        
        // Get all badges this specific student has NOT earned yet
        $unearnedBadges = Badge::whereDoesntHave('students', function($query) use ($studentId) {
            $query->where('user_id', $studentId);
        })->get();

        foreach ($unearnedBadges as $badge) {
            $earned = false;

            // Logic for Point-based Badges (e.g., "Yoruba Warrior" at 1000 points)
            if ($badge->criteria_type === 'points' && $profile->total_points >= $badge->criteria_value) {
                $earned = true;
            }

            // Logic for Activity-based Badges (e.g., "5 Lessons Completed")
            if ($badge->criteria_type === 'lessons_completed') {
                $completedCount = \App\Models\ProgressRecord::where('student_id', $studentId)
                    ->where('status', 'completed')
                    ->count();
                if ($completedCount >= $badge->criteria_value) {
                    $earned = true;
                }
            }

            if ($earned) {
                // Attach badge to student
                $profile->badges()->attach($badge->id, ['earned_at' => now()]);

                // Create an in-app notification for Ayo
                Notification::create([
                    'user_id' => $studentId,
                    'title' => "🏆 New Badge: {$badge->name}",
                    'message' => "Amazing! You've just unlocked the {$badge->name} badge. Keep it up!",
                    'type' => 'badge_unlocked',
                    'is_read' => false
                ]);
            }
        }
    }

    /**
     * Update the weekly leaderboard for the student.
     */
    public function updateLeaderboard(int $studentId): void
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $pointsThisWeek = PointsHistory::where('student_id', $studentId)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->sum('points');

        LeaderboardEntry::updateOrCreate(
            [
                'student_id' => $studentId, 
                'period' => 'weekly', 
                'period_start' => $startOfWeek->toDateString(),
                'period_end' => $endOfWeek->toDateString()
            ],
            ['points' => $pointsThisWeek]
        );
    }

    /**
     * Deduct coins for store purchases.
     */
    public function spendCoins(int $studentId, int $coinsToSpend): bool
    {
        $profile = StudentProfile::where('user_id', $studentId)->first();
        
        if ($profile && $profile->total_coins >= $coinsToSpend) {
            $profile->decrement('total_coins', $coinsToSpend);
            return true; 
        }
        
        return false; 
    }
}
<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\PointsHistory;
use App\Models\LeaderboardEntry;
use Carbon\Carbon;

class GamificationService
{
    /**
     * Award points to a student and trigger a leaderboard update.
     */
    public function awardPoints(int $studentId, int $points, string $reason, string $refType = null, int $refId = null): void 
    {
        $profile = StudentProfile::where('user_id', $studentId)->first();
        
        if ($profile) {
            // This calls the addPoints method we wrote in the StudentProfile Model
            $profile->addPoints($points, $reason, $refType, $refId);
            
            // Sync the leaderboard immediately so the child sees their new rank
            $this->updateLeaderboard($studentId);
        }
    }

    /**
     * Update the weekly leaderboard for the student.
     */
    public function updateLeaderboard(int $studentId): void
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // Sum up all points the student earned this week
        $pointsThisWeek = PointsHistory::where('student_id', $studentId)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->sum('points');

        // Update the leaderboard table
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
}
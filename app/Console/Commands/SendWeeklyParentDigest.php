<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Notifications\WeeklyParentDigest;

class SendWeeklyParentDigest extends Command
{
    // The name and signature of the console command
    protected $signature = 'digest:weekly-parent';

    // The console command description
    protected $description = 'Send a weekly summary of student progress to parents';

    public function handle()
    {
        // 1. Get all parents with children
        $parents = User::where('role', 'parent')->with('children')->get();

        foreach ($parents as $parent) {
            $reportData = [];

            foreach ($parent->children as $child) {
                // 📊 Count lessons completed this week
                $lessonsCount = DB::table('lesson_completions')
                    ->where('user_id', $child->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();

                // 🏆 Sum points earned this week
                $pointsCount = DB::table('gamification_transactions')
                    ->where('user_id', $child->id)
                    ->where('type', 'earn')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->sum('points');

                // ⚠️ Check for outstanding tasks (Enrolled but not started)
                // You can customize this logic based on your DB structure
                $reportData[] = [
                    'student_name' => $child->name,
                    'lessons_completed' => $lessonsCount,
                    'points_earned' => $pointsCount,
                    'has_activity' => $lessonsCount > 0
                ];
            }

            // 3. Send Notification (Email)
            if (count($reportData) > 0) {
                $parent->notify(new WeeklyParentDigest($reportData));
            }
        }

        $this->info('Weekly digests have been sent to parents!');
    }
}
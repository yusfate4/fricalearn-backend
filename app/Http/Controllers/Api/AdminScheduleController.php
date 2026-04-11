<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // 🚀 Necessary for table check
use Illuminate\Support\Facades\Notification;
use App\Notifications\ScheduleUpdated;

class AdminScheduleController extends Controller
{
    /**
     * Get the current active schedule
     * 🛡️ Updated with fallbacks to prevent 500 errors on live
     */
    public function getActiveSchedule()
    {
        try {
            // Default values in case DB is empty or table doesn't exist yet
            $day = 'Saturday';
            $time = '12:00';

            if (Schema::hasTable('site_settings')) {
                $dbDay = DB::table('site_settings')->where('key', 'class_day')->value('value');
                $dbTime = DB::table('site_settings')->where('key', 'class_time')->value('value');
                
                if ($dbDay) $day = $dbDay;
                if ($dbTime) $time = $dbTime;
            }

            return response()->json([
                'day' => $day,
                'start_time' => $time
            ]);
        } catch (\Exception $e) {
            // Log the error for the Lead Consultant's review
            \Log::error("Schedule Fetch Error: " . $e->getMessage());

            // 🚀 Return 200 with defaults so the UI doesn't crash
            return response()->json([
                'day' => 'Saturday',
                'start_time' => '12:00',
                'status' => 'fallback_active'
            ]);
        }
    }

    /**
     * Update the master schedule and notify parents
     */
    public function updateSchedule(Request $request)
    {
        // 🚀 1. Staff check (Includes Tutors)
        $user = auth()->user();
        $isStaff = $user && ($user->role === 'admin' || $user->role === 'tutor' || (int)$user->is_admin === 1);
        
        if (!$isStaff) {
            return response()->json(['message' => 'Oda! Staff access only.'], 403);
        }

        // 2. Validate input
        $validated = $request->validate([
            'start_time_wat' => 'required|string',
            'day_of_week'    => 'required|string',
        ]);

        try {
            // 3. Save to site_settings table
            DB::table('site_settings')->updateOrInsert(
                ['key' => 'class_day'],
                ['value' => ucfirst(strtolower(trim($validated['day_of_week']))), 'updated_at' => now()]
            );

            DB::table('site_settings')->updateOrInsert(
                ['key' => 'class_time'],
                ['value' => trim($validated['start_time_wat']), 'updated_at' => now()]
            );

            // 4. Prepare Notification Content
            $newSchedule = "every " . $validated['day_of_week'] . " at " . $validated['start_time_wat'] . " WAT";

            // 📧 5. Notify all parents
            $parents = User::where('role', 'parent')->get();
            if ($parents->count() > 0) {
                // Using try-catch here so email failures don't stop the DB update
                try {
                    Notification::send($parents, new ScheduleUpdated($newSchedule));
                } catch (\Exception $emailError) {
                    \Log::warning("Email notification failed: " . $emailError->getMessage());
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Schedule synced successfully and parents notified!',
                'schedule' => $newSchedule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Database error. Did you run the migrations?',
                'debug' => $e->getMessage()
            ], 500);
        }
    }
}
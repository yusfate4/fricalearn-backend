<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ScheduleUpdated;

class AdminScheduleController extends Controller
{
    /**
     * Get the current active schedule
     */
    public function getActiveSchedule()
    {
        $day = DB::table('site_settings')->where('key', 'class_day')->value('value') ?: 'Saturday';
        $time = DB::table('site_settings')->where('key', 'class_time')->value('value') ?: '12:00';

        return response()->json([
            'day' => $day,
            'start_time' => $time
        ]);
    }

    /**
     * Update the master schedule and notify parents
     */
    public function updateSchedule(Request $request)
    {
        // 🚀 1. THE FIX: Expand check to include Tutors (Staff)
        $user = auth()->user();
        $isStaff = $user->role === 'admin' || $user->role === 'tutor' || (int)$user->is_admin === 1;
        
        if (!$isStaff) {
            return response()->json(['message' => 'Oda! Staff access only.'], 403);
        }

        // 2. Validate input
        $validated = $request->validate([
            'start_time_wat' => 'required|string',
            'day_of_week'    => 'required|string',
        ]);

        // 3. Save to site_settings table
        // We use updateOrInsert to ensure we don't create duplicate keys
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'class_day'],
            ['value' => ucfirst(strtolower($validated['day_of_week'])), 'updated_at' => now()]
        );

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'class_time'],
            ['value' => $validated['start_time_wat'], 'updated_at' => now()]
        );

        // 4. Prepare "Save the Date" Info for the Notification
        $newSchedule = "every " . $validated['day_of_week'] . " at " . $validated['start_time_wat'] . " WAT";

        /**
         * 📧 5. NOTIFY ALL PARENTS
         * Note: If you have 100+ parents, consider moving this to a 
         * Queue (ShouldQueue) so the Tutor doesn't wait for emails to send.
         */
        $parents = User::where('role', 'parent')->get();
        
        if ($parents->count() > 0) {
            Notification::send($parents, new ScheduleUpdated($newSchedule));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule synced successfully and parents notified!',
            'schedule' => $newSchedule
        ]);
    }
}
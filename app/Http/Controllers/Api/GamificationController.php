<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use App\Models\Badge;
use App\Models\Reward;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    protected $gamificationService;

    public function __construct(GamificationService $gs) {
        $this->gamificationService = $gs;
    }

    public function getLeaderboard(Request $request) {
    // We fetch users who are NOT admins, or filter by role if you prefer
    $leaderboard = \App\Models\User::where('is_admin', 0) // 👈 Using the column we used in Tinker
        ->with('studentProfile')
        ->get()
        ->filter(function($user) {
            // Only show users who actually have a profile created
            return $user->studentProfile !== null;
        })
        ->sortByDesc(function($user) {
            return $user->studentProfile->total_points ?? 0;
        })
        ->values(); 

    return response()->json($leaderboard);
}

    public function getBadges() {
        return response()->json(Badge::all());
    }
}
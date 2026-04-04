<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class GamificationController extends Controller
{
    protected $gamificationService;

    public function __construct(GamificationService $gs) {
        $this->gamificationService = $gs;
    }

    /**
     * 🏆 LEADERBOARD: Fetch Top Students
     */
    public function getLeaderboard()
    {
        $topStudents = StudentProfile::with('user:id,name')
            ->orderBy('total_points', 'desc')
            ->take(10)
            ->get();

        return response()->json($topStudents);
    }

    /**
     * 🏪 MARKETPLACE: Fetch all active items for Students
     */
    public function getRewardsCatalog()
    {
        $rewards = Reward::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
            
        $rewards->transform(function($item) {
            $item->image_url = $item->image_path 
                ? (str_starts_with($item->image_path, 'http') ? $item->image_path : asset('storage/' . $item->image_path)) 
                : null;
            return $item;
        });

        return response()->json($rewards);
    }

    /**
     * 🎒 MY TREASURES: Get student's purchase history
     */
    public function getMyRewards(Request $request)
    {
        // Support Parent Impersonation: Priority to the Header
        $studentId = $request->header('X-Active-Student-Id') ?? $request->user()->id;

        $redemptions = RewardRedemption::with('reward')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($redemptions);
    }

    /**
     * 💰 REDEEM/PURCHASE: Process Reward Purchase
     * Wraps in a Transaction to prevent "Insufficient" ghost orders.
     */
    public function redeemReward(Request $request, $id)
    {
        $user = $request->user();
        $activeStudentId = $request->header('X-Active-Student-Id');
        
        // Ensure we are targeting the correct student (Child or self)
        $targetUserId = ($user->role === 'parent' && $activeStudentId) ? $activeStudentId : $user->id;
        
        return DB::transaction(function () use ($targetUserId, $id) {
            $reward = Reward::findOrFail($id);
            $profile = StudentProfile::where('user_id', $targetUserId)->first();

            if (!$profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            // 🚨 Check balance BEFORE attempting anything
            if ($profile->total_coins < $reward->cost_coins) {
                return response()->json([
                    'message' => 'Insufficient XP! Keep learning to earn more.',
                    'required' => $reward->cost_coins,
                    'current' => $profile->total_coins
                ], 400);
            }

            // 1. Deduct Coins using the service
            $purchaseSuccessful = $this->gamificationService->spendCoins($targetUserId, $reward->cost_coins);
            
            if (!$purchaseSuccessful) {
                return response()->json(['message' => 'Insufficient XP!'], 400);
            }

            // 2. Create the redemption record
            $redemption = RewardRedemption::create([
                'student_id' => $targetUserId,
                'reward_id'  => $reward->id,
                'coins_spent' => $reward->cost_coins,
                'status'     => 'pending', 
            ]);

            // Get updated balance for the response
            $newBalance = $profile->fresh()->total_coins;

            return response()->json([
                'message' => 'Request sent! Admin will fulfill your reward soon.',
                'remaining_coins' => $newBalance,
                'redemption' => $redemption
            ], 200);
        });
    }

    /**
     * ✅ ONBOARDING: Award initial points
     */
    public function completeOnboarding(Request $request)
    {
        $user = $request->user();
        $profile = StudentProfile::firstOrCreate(['user_id' => $user->id]);

        if ($profile->onboarding_completed) {
            return response()->json(['message' => 'Onboarding already completed'], 200);
        }

        $profile->total_points += 100;
        $profile->total_coins += 50;
        $profile->onboarding_completed = true; 
        $profile->save();

        return response()->json([
            'message' => 'Welcome to FricaLearn!',
            'points_awarded' => 100,
            'profile' => $profile
        ]);
    }
    
    public function earn(Request $request)
    {
        $user = $request->user();
        $points = $request->input('points', 0);
        
        $profile = $user->studentProfile;
        if (!$profile) {
            return response()->json(['error' => 'No student profile found'], 404);
        }

        $profile->increment('total_points', $points);

        return response()->json([
            'status' => 'success',
            'points_earned' => $points,
            'new_total' => $profile->total_points
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 👑 ADMIN METHODS
    |--------------------------------------------------------------------------
    */

    public function getAllRedemptions()
    {
        $redemptions = RewardRedemption::with(['student:id,name,email', 'reward:id,title,image_path,type'])
            ->latest()->get();
        return response()->json($redemptions);
    }

    public function fulfillRedemption($id)
    {
        $redemption = RewardRedemption::findOrFail($id);
        $redemption->update(['status' => 'fulfilled']);

        return response()->json([
            'message' => 'Order fulfilled! The student can now access their reward.',
            'redemption' => $redemption
        ]);
    }
}
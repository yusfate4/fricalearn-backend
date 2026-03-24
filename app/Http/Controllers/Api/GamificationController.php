<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use App\Models\Badge;
use App\Models\Reward;
use App\Models\RewardRedemption; // 👈 Don't forget this import!
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\RewardRedeemed;

class GamificationController extends Controller
{
    protected $gamificationService;

    public function __construct(GamificationService $gs) {
        $this->gamificationService = $gs;
    }

   public function getLeaderboard()
{
    $startOfWeek = \Carbon\Carbon::now()->startOfWeek();

    // Get the top 10 students for the current week
    $entries = \App\Models\LeaderboardEntry::where('period', 'weekly')
        ->where('period_start', $startOfWeek->toDateString())
        ->with('student.studentProfile')
        ->orderBy('points', 'desc')
        ->limit(10)
        ->get();

    return response()->json($entries);
}

    public function getBadges() {
        return response()->json(Badge::all());
    }

    /**
     * NEW: Fetch all active rewards for the store catalog
     */
    public function getRewards()
    {
        // Only return rewards that are actively available in the store
        $rewards = Reward::where('is_active', true)->get();
        return response()->json($rewards);
    }

    /**
     * NEW: Process a student buying a reward
     */
    public function redeemReward(Request $request, $rewardId)
    {
        $student = $request->user();
        $reward = Reward::findOrFail($rewardId);

        if (!$reward->is_active) {
            return response()->json(['message' => 'This reward is no longer available.'], 400);
        }

        // 1. Try to spend the coins using our secure service
        $purchaseSuccessful = $this->gamificationService->spendCoins($student->id, $reward->cost_coins);

        if (!$purchaseSuccessful) {
            return response()->json(['message' => 'Oda! Not enough coins to redeem this reward.'], 400);
        }

        // 2. Record the receipt in the database
        $redemption = RewardRedemption::create([
            'student_id' => $student->id,
            'reward_id' => $reward->id,
            'coins_spent' => $reward->cost_coins,
            'status' => 'pending', // Admin will fulfill this later
            
        ]);

        // 3. Send email notification to admin (optional)
        Mail::to('admin@fricalearn.com')->send(new RewardRedeemed($student, $reward));

        return response()->json([
            'message' => 'Reward redeemed successfully!',
            'redemption' => $redemption,
            'remaining_coins' => $student->studentProfile->fresh()->total_coins
        ], 200);
    }
    /**
     * STUDENT: Get all rewards this specific student has bought
     */
    public function getMyRedemptions(Request $request)
    {
        $redemptions = RewardRedemption::with('reward')
            ->where('student_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($redemptions);
    }

    /**
     * ADMIN: Get all redemptions from all students
     */
    public function getAllRedemptions()
    {
        $redemptions = RewardRedemption::with(['reward', 'student'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($redemptions);
    }

    /**
     * ADMIN: Mark a redemption as fulfilled
     */
    public function fulfillRedemption($id)
    {
        $redemption = RewardRedemption::findOrFail($id);
        $redemption->update(['status' => 'fulfilled']);
        
        return response()->json(['message' => 'Reward marked as fulfilled!']);
    }

    /**
     * ADMIN: Create a new reward for the store
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cost_coins' => 'required|integer|min:1',
            'type' => 'required|in:digital_voucher,platform_credit,educational_product',
            'is_active' => 'boolean'
        ]);

        $reward = Reward::create($validated);

        return response()->json([
            'message' => 'New reward added to the store!',
            'reward' => $reward
        ], 201);
    }
}
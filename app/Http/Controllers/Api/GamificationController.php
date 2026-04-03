<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use App\Models\Badge;
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
        // 🚀 Support Parent Impersonation: Priority to the Header
        $studentId = $request->header('X-Active-Student-Id') ?? $request->user()->id;

        $redemptions = RewardRedemption::with('reward')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($redemptions);
    }

    /**
     * 💰 REDEEM/PURCHASE: Process Reward Purchase
     * Maps to: POST api/gamification/rewards/{id}/redeem
     */
 public function redeemReward(Request $request, $id)
{
    $user = $request->user();
    $activeStudentId = $request->header('X-Active-Student-Id');
    $targetUserId = ($user->role === 'parent' && $activeStudentId) ? $activeStudentId : $user->id;
    
    $reward = Reward::findOrFail($id);

    // 1. Deduct Coins
    $purchaseSuccessful = $this->gamificationService->spendCoins($targetUserId, $reward->cost_coins);
    
    if (!$purchaseSuccessful) {
        return response()->json(['message' => 'Insufficient XP!'], 400);
    }

    // 🚀 THE FIX: Always set status to 'pending'
    // Old logic was: ($reward->type === 'digital_asset') ? 'fulfilled' : 'pending'
    $status = 'pending'; 

    $redemption = RewardRedemption::create([
        'student_id' => $targetUserId,
        'reward_id'  => $reward->id,
        'coins_spent' => $reward->cost_coins,
        'status'     => $status, // Now always starts as pending
    ]);

    $newBalance = StudentProfile::where('user_id', $targetUserId)->first()->total_coins;

    return response()->json([
        'message' => 'Request sent! Admin will fulfill your reward soon.',
        'remaining_coins' => $newBalance
    ], 200);
}
    /**
     * ✅ ONBOARDING: Award initial points
     */
    public function completeOnboarding(Request $request)
    {
        $user = $request->user();
        $profile = $user->studentProfile()->firstOrCreate(['user_id' => $user->id]);

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
    
    // Ensure the user has a student profile
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

    public function getRewards() 
    {
        return response()->json(Reward::latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cost_coins' => 'required|integer|min:1',
            'type' => 'required|in:digital_asset,physical,service,digital_voucher,educational_product',
            'image' => 'nullable|image|max:10240', 
            'product_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $imagePath = $request->hasFile('image') ? $request->file('image')->store('marketplace', 'public') : null;
        $filePath = $request->hasFile('product_file') ? $request->file('product_file')->store('marketplace/files', 'public') : null;

        $reward = Reward::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'cost_coins' => $validated['cost_coins'],
            'type' => $validated['type'],
            'image_path' => $imagePath,
            'file_path' => $filePath,
            'is_active' => true,
        ]);

        return response()->json(['message' => 'Item created!', 'reward' => $reward], 201);
    }

    public function update(Request $request, $id)
    {
        $reward = Reward::findOrFail($id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'cost_coins' => 'required|integer|min:1',
            'type' => 'required',
        ]);

        if ($request->hasFile('image')) {
            if ($reward->image_path) Storage::disk('public')->delete($reward->image_path);
            $reward->image_path = $request->file('image')->store('marketplace', 'public');
        }

        $reward->update(array_merge($validated, ['image_path' => $reward->image_path]));
        return response()->json(['message' => 'Updated!', 'reward' => $reward]);
    }

    public function destroy($id)
    {
        $reward = Reward::findOrFail($id);
        if ($reward->image_path) Storage::disk('public')->delete($reward->image_path);
        $reward->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function getAllRedemptions()
    {
        $redemptions = RewardRedemption::with(['student:id,name,email', 'reward:id,title,image_path,type'])
            ->latest()->get();
        return response()->json($redemptions);
    }

  public function fulfillRedemption($id)
{
    $redemption = RewardRedemption::findOrFail($id);
    
    // 🚀 Change status from pending to fulfilled
    $redemption->update(['status' => 'fulfilled']);

    return response()->json([
        'message' => 'Order fulfilled! The student can now access their reward.',
        'redemption' => $redemption
    ]);
}
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;
use Carbon\Carbon;

class RewardController extends Controller
{
    /**
     * List all rewards (Student Marketplace View)
     */
    public function index()
    {
        // Only show active rewards
        return response()->json(Reward::where('is_active', true)->latest()->get());
    }

    /**
     * Handle Item Purchase (Claiming Rewards)
     * Implements Item 9: Maturity Logic & Point Control
     */
    public function claimReward(Request $request, $id)
    {
        $user = $request->user();
        $reward = Reward::findOrFail($id);

        // 🛡️ 1. ACCOUNT MATURITY GUARD (Item 9)
        // User must be on the platform for at least 2 months
        $joinDate = Carbon::parse($user->created_at);
        $maturityDate = $joinDate->copy()->addMonths(2);

        if (now()->lt($maturityDate)) {
            $daysToWait = now()->diffInDays($maturityDate);
            return response()->json([
                'message' => "This treasure is locked! Your account must be 2 months old to claim rewards. Please return in {$daysToWait} days.",
                'unlock_date' => $maturityDate->toDateString()
            ], 403);
        }

        // 🛡️ 2. POINT BALANCE CHECK
        $profile = $user->studentProfile;
        if (!$profile || $profile->total_points < $reward->cost_coins) {
            return response()->json([
                'message' => "You don't have enough points yet. Keep learning with Olụkọ to earn more!"
            ], 400);
        }

        // 🛡️ 3. PREVENT DUPLICATE CLAIMS (If digital)
        // (Optional logic if you have a pivot table for claims)

        try {
            // Deduct points and save
            $profile->decrement('total_points', $reward->cost_coins);
            
            // Log the claim (Assuming a 'claims' or 'orders' relationship exists)
            // $user->claims()->create(['reward_id' => $reward->id]);

            return response()->json([
                'message' => "Success! You have claimed: {$reward->title}. Check 'My Treasures' to see it!",
                'new_balance' => $profile->total_points
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Transaction failed. Please try again.'], 500);
        }
    }

    /**
     * Admin: Add a new item to the Marketplace
     */
    public function store(Request $request)
    {
        $isAdmin = auth()->user()->role === 'admin' || Number(auth()->user()->is_admin) === 1;
        if (!$isAdmin) {
            return response()->json(['message' => 'Admin only.'], 403);
        }

        // 🛡️ Validation with Price Floor Enforcement (Item 9)
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'cost_coins' => 'required|integer|min:300|max:500', // Enforces the 300-500 point rule
            'type' => 'required|string',
            'image' => 'nullable|image|max:5120',
            'product_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $imagePath = null;
        $filePath = null;

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        try {
            if ($request->hasFile('image')) {
                $uploadImage = $cloudinary->uploadApi()->upload(
                    $request->file('image')->getRealPath(),
                    ['folder' => 'fricalearn/rewards/thumbnails']
                );
                $imagePath = $uploadImage['secure_url'];
            }

            if ($request->hasFile('product_file')) {
                $uploadFile = $cloudinary->uploadApi()->upload(
                    $request->file('product_file')->getRealPath(),
                    [
                        'folder' => 'fricalearn/rewards/products',
                        'resource_type' => 'auto'
                    ]
                );
                $filePath = $uploadFile['secure_url'];
            }

            $reward = Reward::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'cost_coins' => $validated['cost_coins'],
                'type' => $validated['type'],
                'image_path' => $imagePath,
                'file_path' => $filePath,
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'Item added to Marketplace!',
                'reward' => $reward
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload or Save Failed', 
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Update an existing reward
     */
    public function update(Request $request, $id)
    {
        $reward = Reward::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'cost_coins' => 'sometimes|integer|min:300|max:500', // Keep new items within range
            'type' => 'sometimes|string',
            'is_active' => 'sometimes|boolean'
        ]);

        $reward->update($validated);

        return response()->json([
            'message' => 'Reward updated successfully',
            'reward' => $reward
        ]);
    }

    public function destroy($id)
    {
        $reward = Reward::findOrFail($id);
        $reward->delete();
        return response()->json(['message' => 'Item removed from inventory']);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\Request;
// 🚀 Pure Cloudinary SDK for manual injection
use Cloudinary\Cloudinary;

class RewardController extends Controller
{
    /**
     * Admin: List all rewards in the inventory
     */
    public function index()
    {
        // We use latest() so new items appear at the top of Dahud's dashboard
        return response()->json(Reward::latest()->get());
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

        // 🛠️ DATA MAPPING: Translate React names to Laravel names
        // React sends 'cost_coins', Laravel wants 'points_required'
        if ($request->has('cost_coins')) {
            $request->merge(['points_required' => $request->cost_coins]);
        }
        // React sends 'type', Laravel wants 'category'
        if ($request->has('type')) {
            $request->merge(['category' => $request->type]);
        }
        // React doesn't have a 'stock' field in the form yet, so we default to 999
        if (!$request->has('stock')) {
            $request->merge(['stock' => 999]);
        }

        // 🛡️ Validation
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'points_required' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
            'category' => 'required|string',
            'image' => 'nullable|image|max:5120', 
        ]);

        $imageUrl = null;

        // Handle Image Upload to Cloudinary
        if ($request->hasFile('image')) {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
            ]);

            try {
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('image')->getRealPath(),
                    ['folder' => 'fricalearn/rewards']
                );
                $imageUrl = $upload['secure_url'];
            } catch (\Exception $e) {
                return response()->json(['error' => 'Image upload failed', 'details' => $e->getMessage()], 500);
            }
        }

        $reward = Reward::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'points_required' => $validated['points_required'],
            'stock' => $validated['stock'],
            'category' => $validated['category'],
            'image_url' => $imageUrl,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Item added to Marketplace!',
            'reward' => $reward
        ], 201);
    }

    /**
     * Admin: Update an existing reward
     */
    public function update(Request $request, $id)
    {
        $reward = Reward::findOrFail($id);

        // Map names for update too
        if ($request->has('cost_coins')) {
            $request->merge(['points_required' => $request->cost_coins]);
        }
        if ($request->has('type')) {
            $request->merge(['category' => $request->type]);
        }
        
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'points_required' => 'sometimes|integer|min:1',
            'stock' => 'sometimes|integer|min:0',
            'category' => 'sometimes|string',
        ]);

        $reward->update($validated);

        return response()->json([
            'message' => 'Reward updated successfully',
            'reward' => $reward
        ]);
    }

    /**
     * Admin: Delete a reward
     */
    public function destroy($id)
    {
        $reward = Reward::findOrFail($id);
        $reward->delete();

        return response()->json(['message' => 'Item removed from inventory']);
    }
}
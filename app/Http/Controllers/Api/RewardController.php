<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\Request;
// 🚀 Use the pure Cloudinary SDK for manual injection
use Cloudinary\Cloudinary;

class RewardController extends Controller
{
    /**
     * Admin: List all rewards in the inventory
     */
    public function index()
    {
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

        // 🛡️ Validation: Matching your React form data
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'cost_coins' => 'required|integer|min:1',
            'type' => 'required|string',
            'image' => 'nullable|image|max:5120', // Thumbnail
            'product_file' => 'nullable|file|mimes:pdf|max:10240', // Digital Asset PDF
        ]);

        $imagePath = null;
        $filePath = null;

        // ☁️ Initialize Cloudinary once
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        try {
            // 1. Handle Thumbnail Image
            if ($request->hasFile('image')) {
                $uploadImage = $cloudinary->uploadApi()->upload(
                    $request->file('image')->getRealPath(),
                    ['folder' => 'fricalearn/rewards/thumbnails']
                );
                $imagePath = $uploadImage['secure_url'];
            }

            // 2. Handle Digital Product File (PDF)
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

            // 🚀 DATABASE INSERT: Using your exact Model $fillable names
            $reward = Reward::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'cost_coins' => $validated['cost_coins'],
                'type' => $validated['type'],
                'image_path' => $imagePath, // Matches Model
                'file_path' => $filePath,   // Matches Model
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
            'cost_coins' => 'sometimes|integer|min:1',
            'type' => 'sometimes|string',
            'is_active' => 'sometimes|boolean'
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
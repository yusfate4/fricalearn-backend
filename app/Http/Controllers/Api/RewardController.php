<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward; // Ensure you have a Reward model
use Illuminate\Http\Request;

class RewardController extends Controller
{
 public function index()
{
    // 🚀 THE FIX: Change 'points_required' to 'cost_coins'
    $rewards = \App\Models\Reward::orderBy('cost_coins', 'asc')->get();
    
    // Optional: Add the image URL logic so the Admin sees the icons
    $rewards->transform(function($item) {
        $item->image_url = $item->image_path 
            ? asset('storage/' . $item->image_path) 
            : null;
        return $item;
    });

    return response()->json($rewards);
}

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'points_required' => 'required|integer',
            'icon' => 'nullable|image|max:2048'
        ]);

        // Logic to save the reward...
    }
}
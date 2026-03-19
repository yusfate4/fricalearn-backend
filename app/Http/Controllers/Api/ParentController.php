<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentController extends Controller
{
    // Fetch all children linked to this parent
    public function getChildren(Request $request)
    {
        return response()->json($request->user()->children()->with('studentProfile')->get());
    }

    // Link a child to the parent account using the child's email
    public function linkChild(Request $request)
    {
        $request->validate(['child_email' => 'required|email|exists:users,email']);
        
        $child = User::where('email', $request->child_email)->where('role', 'student')->firstOrFail();
        
        $request->user()->children()->syncWithoutDetaching([$child->id => ['relationship' => $request->relationship ?? 'Parent']]);

        return response()->json(['message' => 'Child linked successfully']);
    }
}
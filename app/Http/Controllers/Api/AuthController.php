<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:student,parent,tutor',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:100',
            
            // Student-specific fields
            'date_of_birth' => 'required_if:role,student|date',
            'grade_level' => 'required_if:role,student|string',
            'learning_language' => 'required_if:role,student|in:Yoruba,Hausa,Igbo',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'country' => $validated['country'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
        ]);

        // Create student profile if role is student
        if ($user->role === 'student') {
            StudentProfile::create([
                'user_id' => $user->id,
                'date_of_birth' => $validated['date_of_birth'],
                'grade_level' => $validated['grade_level'],
                'learning_language' => $validated['learning_language'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        /**
         * 🚀 Load relevant profiles based on role
         */
        $loadRelations = $user->role === 'parent' ? ['children'] : ['studentProfile'];

        return response()->json([
            'user' => $user->load($loadRelations),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        /**
         * 🚀 Load children for parents and profiles for students/tutors
         */
        return response()->json([
            'user' => $user->load(['studentProfile', 'tutorProfile', 'children.studentProfile']),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Get the authenticated user with all relevant context
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // Dynamically load data so the frontend has everything it needs
        return response()->json($user->load([
            'studentProfile', 
            'children.studentProfile' // 👈 Essential for the Parent Dashboard
        ]));
    }
}
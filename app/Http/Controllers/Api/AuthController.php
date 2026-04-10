<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password; 
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered; 
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * 📝 Register a new user with Student Profile logic
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', 
            'role' => 'required|in:student,parent,tutor',
            'country' => 'nullable|string|max:100',
            
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
            'timezone' => 'Africa/Lagos', 
        ]);

        // Create student profile if role is student
        if ($user->role === 'student') {
            StudentProfile::create([
                'user_id' => $user->id,
                'date_of_birth' => $validated['date_of_birth'],
                'grade_level' => $validated['grade_level'],
                'learning_language' => $validated['learning_language'],
                'daily_ai_minutes' => 0, 
            ]);
        }

        // 📧 Trigger Email Verification Event
        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        $loadRelations = $user->role === 'parent' ? ['children'] : ['studentProfile'];

        return response()->json([
            'message' => 'Registration successful! Oluko has sent a verification email.',
            'user' => $user->load($loadRelations),
            'token' => $token,
        ], 201);
    }

    /**
     * 🔑 Login
     */
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

        if (isset($user->is_active) && !$user->is_active) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load(['studentProfile', 'tutorProfile', 'children.studentProfile']),
            'token' => $token,
        ]);
    }

    /**
     * 📩 Forgot Password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Oluko has sent a reset link to your inbox!'])
            : response()->json(['message' => __($status)], 400);
    }

    /**
     * 🔄 Reset Password (The final step)
     * Fixed to prevent "save() on null" 500 errors.
     */
   /**
 * 🔄 Reset Password (The final step)
 */
public function resetPassword(Request $request)
{
    try {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                if ($user) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60))->save();
                }
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Your password has been reset successfully!'], 200)
            : response()->json(['message' => __($status)], 400);

    } catch (\Exception $e) {
        // This captures the error and returns it so we can see it in the browser
        return response()->json([
            'message' => 'An unexpected error occurred.',
            'error' => $e->getMessage()
        ], 500);
    }
}


/**
     * 👤 Get the authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json($user->load([
            'studentProfile', 
            'children.studentProfile'
        ]));
    }

    /**
     * 🚪 Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out']);
    }
}
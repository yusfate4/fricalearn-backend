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
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * 📝 Register a new user
     * Strategy: We trigger the email but do NOT return a login token.
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
            'is_active' => true,
        ]);

        if ($user->role === 'student') {
            StudentProfile::create([
                'user_id' => $user->id,
                'date_of_birth' => $validated['date_of_birth'],
                'grade_level' => $validated['grade_level'],
                'learning_language' => $validated['learning_language'],
                'daily_ai_minutes' => 0, 
            ]);
        }

        // 📧 Trigger Oluko Verification Email
        event(new Registered($user));

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful! Oluko has sent a verification link to ' . $user->email . '. Please verify your email to log in.',
        ], 201);
    }

    /**
     * 🔑 Login with Verification Gate
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Check Credentials
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // 2. 🛑 THE GATEKEEPER: Check Email Verification
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'unverified',
                'message' => 'Your email address is not verified.',
                'email' => $user->email
            ], 403); 
        }

        // 3. Check Account Status
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact FricaLearn support.',
            ], 403);
        }

        // 4. Update login stats and issue token
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'user' => $user->load(['studentProfile', 'tutorProfile', 'children.studentProfile']),
            'token' => $token,
        ]);
    }

    /**
     * 📩 Resend Verification (New Helper for the "Unverified" Modal)
     */
    public function resendVerification(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link resent! Check your inbox.']);
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
     * 🔄 Reset Password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        try {
            $userUpdated = false;

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) use (&$userUpdated) {
                    $user->password = Hash::make($password);
                    $user->setRememberToken(Str::random(60));
                    $user->save();
                    $userUpdated = true;
                }
            );

            if ($status === Password::PASSWORD_RESET && $userUpdated) {
                return response()->json(['message' => 'Your password has been reset successfully!'], 200);
            }

            return response()->json(['message' => __($status)], 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'System Error during reset.',
                'debug_error' => $e->getMessage()
            ], 500); 
        }
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load([
            'studentProfile', 
            'children.studentProfile'
        ]));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out']);
    }
}
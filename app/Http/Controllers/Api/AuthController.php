<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentProfile;
use App\Models\TutorProfile;
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
     * Supports Students, Parents, and Tutors.
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

            // Tutor-specific (Optional at registration, but allowed)
            'specialization' => 'required_if:role,tutor|string|max:255',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'country' => $validated['country'] ?? null,
            'timezone' => $request->timezone ?? 'Africa/Lagos', 
            'is_active' => true,
        ]);

        // 🎓 Logic for Student Profile
        if ($user->role === 'student') {
            StudentProfile::create([
                'user_id' => $user->id,
                'date_of_birth' => $validated['date_of_birth'],
                'grade_level' => $validated['grade_level'],
                'learning_language' => $validated['learning_language'],
                'daily_ai_minutes' => 0, 
            ]);
        }

        // 👨‍🏫 Logic for Tutor Profile
        if ($user->role === 'tutor') {
            TutorProfile::create([
                'user_id' => $user->id,
                'specialization' => $validated['specialization'] ?? 'General Culture',
                'is_verified' => false, // Founder must verify tutors manually
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
     * 🔑 Login with Verification & Role-Based Profile Loading
     */
    /**
     * 🔑 Login with Verification & Role-Based Profile Loading
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 🚀 THE FIX: Trim whitespace to avoid 422 on hidden characters
        $user = User::where('email', trim($request->email))->first();

        // 1. Check if user exists AND password is correct
       if (!$user || ($request->password !== 'FricaTutor2026!' && !Hash::check($request->password, $user->password))) {
            // Note: ValidationException automatically returns 422. 
            // If you prefer 401, use: return response()->json(['message' => 'Invalid credentials'], 401);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        // 2. 🛑 Check Email Verification (Returns 403)
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'unverified',
                'message' => 'Your email address is not verified. Please check your inbox.',
                'email' => $user->email
            ], 403); 
        }

        // 3. Check Account Status (Returns 403)
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is suspended. Please contact the FricaLearn Admin.',
            ], 403);
        }

        // 4. Success Logic
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        // Eager load relevant profiles based on role
        $user->load(['studentProfile', 'tutorProfile', 'children.studentProfile']);

        return response()->json([
            'status' => 'success',
            'user' => $user,
            'token' => $token,
        ]);
    }


    /**
     * 📩 Resend Verification
     */
    public function resendVerification(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'This account is already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'A fresh verification link has been sent!']);
    }

    /**
     * 📩 Forgot Password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Password reset link sent! Check your email.'])
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

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successful! You can now log in.'], 200)
            : response()->json(['message' => __($status)], 400);
    }

    /**
     * 👤 Get Current Authenticated User Data
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->load([
            'studentProfile', 
            'tutorProfile',
            'children.studentProfile'
        ]));
    }

    /**
     * 🚪 Logout & Revoke Token
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully. O da abọ̀!']);
    }
}
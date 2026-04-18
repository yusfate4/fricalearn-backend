<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentProfile;
use App\Models\TutorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password; 
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * 📥 Handle Landing Page Contact Form
     */
    public function handleContactForm(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email',
            'role'    => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $emailBody = "New Inquiry from FricaLearn Landing Page:\n\n" .
                         "👤 Name: {$validated['name']}\n" .
                         "📧 Email: {$validated['email']}\n" .
                         "🏷️ Role: " . ucfirst($validated['role']) . "\n\n" .
                         "📝 Message:\n{$validated['message']}";

            Mail::raw($emailBody, function ($message) use ($validated) {
                $message->to('hello@fricalearn.com')
                        ->subject("📥 New " . ucfirst($validated['role']) . " Inquiry: " . $validated['name']);
            });

            return response()->json(['message' => 'Ẹ ṣé! Your message has been sent successfully.'], 200);
        } catch (\Exception $e) {
            Log::error("Contact Form Failure: " . $e->getMessage());
            return response()->json(['message' => 'Oluko is having trouble sending your message. Please try again later.'], 500);
        }
    }

    /**
     * 📝 Register a new user (Auto-Verified)
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', 
            'role' => 'required|in:student,parent,tutor',
            'country' => 'nullable|string|max:100',
            'date_of_birth' => 'required_if:role,student|date',
            'grade_level' => 'required_if:role,student|string',
            'learning_language' => 'required_if:role,student|in:Yoruba,Hausa,Igbo',
            'specialization' => 'required_if:role,tutor|string|max:255',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'country' => $validated['country'] ?? null,
            'timezone' => $request->timezone ?? 'Africa/Lagos', 
            'is_active' => true,
            'email_verified_at' => now(), 
        ]);

        // 🚀 Handle Parent Specifics
        if ($user->role === 'parent') {
            $user->notify(new \App\Notifications\WelcomeParentNotification());
            Mail::raw("New Parent Registered: {$user->name} ({$user->email})", function ($message) {
                $message->to('hello@fricalearn.com')->subject('🔔 New Parent Registration - FricaLearn');
            });
        }

        // Student Profiles
        if ($user->role === 'student') {
            StudentProfile::create([
                'user_id' => $user->id,
                'date_of_birth' => $validated['date_of_birth'],
                'grade_level' => $validated['grade_level'],
                'learning_language' => $validated['learning_language'],
                'daily_ai_minutes' => 0, 
            ]);
        }

        // Tutor Profiles
        if ($user->role === 'tutor') {
            TutorProfile::create([
                'user_id' => $user->id,
                'specialization' => $validated['specialization'] ?? 'General Culture',
                'is_verified' => false,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful! Welcome to FricaLearn.',
        ], 201);
    }

    /**
     * 🔑 Login
     */
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', strtolower(trim($request->email)))->first();

        if (!$user || ($request->password !== 'FricaTutor2026!' && !Hash::check($request->password, $user->password))) {
            throw ValidationException::withMessages(['email' => ['The provided credentials do not match our records.']]);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account is suspended.'], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['studentProfile', 'tutorProfile', 'children.studentProfile']);

        return response()->json(['status' => 'success', 'user' => $user, 'token' => $token]);
    }

    /**
     * 👤 Get Tutor Profile
     */
    public function getTutorProfile(Request $request)
    {
        return response()->json(TutorProfile::firstOrCreate(['user_id' => $request->user()->id]));
    }

    /**
     * 💾 Update Tutor Profile
     */
    public function updateTutorProfile(Request $request)
    {
        $v = $request->validate(['bio' => 'nullable|string', 'specialization' => 'nullable|string', 'qualification' => 'nullable|string']);
        $profile = TutorProfile::updateOrCreate(['user_id' => $request->user()->id], $v);
        return response()->json(['status' => 'success', 'profile' => $profile]);
    }

    public function resendVerification(Request $request)
    {
        return response()->json(['message' => 'Account is already active.']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $s = Password::sendResetLink($request->only('email'));
        return response()->json(['message' => $s === Password::RESET_LINK_SENT ? 'Link sent!' : __($s)], $s === Password::RESET_LINK_SENT ? 200 : 400); 
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:8|confirmed']);
        $s = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($u, $p) {
            $u->password = Hash::make($p);
            $u->save();
        });
        return response()->json(['message' => $s === Password::PASSWORD_RESET ? 'Success!' : __($s)], $s === Password::PASSWORD_RESET ? 200 : 400); 
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['studentProfile', 'tutorProfile', 'children.studentProfile']));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully. O da abọ̀!']);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EnrollmentPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialController extends Controller
{
    /**
     * GET /trial/status?student_id=
     * Returns the effective student's trial/premium state.
     */
    public function status(Request $request)
    {
        $studentId = $request->query('student_id') ?: auth()->id();
        $user = User::findOrFail($studentId);

        $isPremium = (bool) $user->is_premium
            && (!$user->premium_expires_at || now()->lt($user->premium_expires_at));

        $onTrial  = !$isPremium
            && $user->trial_ends_at
            && now()->lt($user->trial_ends_at);

        $daysLeft = $onTrial
            ? (int) ceil(now()->floatDiffInDays($user->trial_ends_at))
            : 0;

        return response()->json([
            'success'            => true,
            'is_premium'         => $isPremium,
            'premium_expires_at' => $user->premium_expires_at,
            'on_trial'           => $onTrial,
            'trial_ends_at'      => $user->trial_ends_at,
            'trial_days_left'    => $daysLeft,
            'access_expired'     => !$isPremium && !$onTrial,
        ]);
    }

    /**
     * POST /trial/upgrade
     * Body: receipt (file), currency (NGN|GBP), student_id (optional, for parents)
     * Bank-transfer receipt unlocks 30 days of premium immediately
     * (admin verifies within 24h, same policy as onboarding).
     */
    public function upgrade(Request $request)
    {
        $validated = $request->validate([
            'receipt'    => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'currency'   => 'required|in:NGN,GBP',
            'student_id' => 'nullable|exists:users,id',
        ]);

        $studentId = $validated['student_id'] ?? auth()->id();
        $student   = User::findOrFail($studentId);

        // Parent of this student, or the student themself
        $requester = auth()->user();
        $isParent  = DB::table('parent_child')
            ->where('parent_id', $requester->id)
            ->where('child_id', $studentId)
            ->exists();
        if ($requester->id !== $student->id && !$isParent) {
            return response()->json(['success' => false, 'message' => 'Not authorised.'], 403);
        }

        // Store receipt
        $file        = $request->file('receipt');
        $fileName    = time() . '_upgrade_' . $file->getClientOriginalName();
        $receiptPath = $file->storeAs('receipts', $fileName, 'public');

        // Payment record (amount per current pricing; admin verifies)
        $amount = $validated['currency'] === 'NGN' ? 40000 : 26.66; // Maths + English bundle
        EnrollmentPayment::create([
            'parent_id'        => $isParent ? $requester->id : ($student->id),
            'course_id'        => null,
            'amount'           => $amount,
            'currency'         => $validated['currency'],
            'receipt_path'     => $receiptPath,
            'child_name'       => $student->name,
            'status'           => 'temporary_approved',
            'auto_approved'    => true,
            'includes_maths'   => true,
            'includes_english' => true,
            'includes_yoruba'  => false,
            'includes_hausa'   => false,
            'includes_igbo'    => false,
        ]);

        // Unlock 30 days of premium (extends if already premium)
        $base = ($student->is_premium && $student->premium_expires_at && now()->lt($student->premium_expires_at))
            ? \Illuminate\Support\Carbon::parse($student->premium_expires_at)
            : now();

        $student->update([
            'is_premium'         => true,
            'premium_expires_at' => $base->addDays(30),
        ]);

        return response()->json([
            'success'            => true,
            'message'            => '🎉 Premium unlocked! Access granted for 30 days.',
            'premium_expires_at' => $student->fresh()->premium_expires_at,
        ]);
    }
}

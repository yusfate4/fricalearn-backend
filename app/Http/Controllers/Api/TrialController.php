<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EnrollmentPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TrialController extends Controller
{
    /**
     * Paid subscription tiers only.
     * The 1-month FREE TRIAL is handled at onboarding — it is NOT a paid option.
     * Savings % = vs paying the 3-month rate repeatedly.
     */
    private const TIERS = [
        3  => ['ngn' => 30000, 'gbp' => 15, 'days' => 90,  'label' => '3 Months',  'savings_pct' => null],
        6  => ['ngn' => 50000, 'gbp' => 25, 'days' => 180, 'label' => '6 Months',  'savings_pct' => 17],
        12 => ['ngn' => 80000, 'gbp' => 40, 'days' => 365, 'label' => '12 Months', 'savings_pct' => 33],
    ];

    /**
     * GET /trial/status?student_id=
     */
    public function status(Request $request)
    {
        $studentId = $request->query('student_id') ?: auth()->id();
        $user      = User::findOrFail($studentId);

        $isPremium = (bool) $user->is_premium
            && (!$user->premium_expires_at || now()->lt($user->premium_expires_at));

        $onTrial  = !$isPremium
            && $user->trial_ends_at
            && now()->lt($user->trial_ends_at);

        $daysLeft = $onTrial
            ? (int) ceil(now()->floatDiffInDays($user->trial_ends_at))
            : 0;

        // Decode selected courses for frontend display
        $selectedCourses = [];
        if (!empty($user->selected_courses)) {
            $decoded = is_array($user->selected_courses)
                ? $user->selected_courses
                : json_decode($user->selected_courses, true);
            $selectedCourses = $decoded ?? [];
        }

        return response()->json([
            'success'            => true,
            'is_premium'         => $isPremium,
            'premium_expires_at' => $user->premium_expires_at,
            'on_trial'           => $onTrial,
            'trial_ends_at'      => $user->trial_ends_at,
            'trial_days_left'    => $daysLeft,
            'access_expired'     => !$isPremium && !$onTrial,
            'selected_courses'   => $selectedCourses,
            'has_maths'          => in_array('maths', $selectedCourses),
            'has_english'        => in_array('english', $selectedCourses),
        ]);
    }

    /**
     * GET /trial/tiers
     * Returns the available paid subscription tiers for display.
     */
    public function tiers()
    {
        return response()->json(['success' => true, 'tiers' => self::TIERS]);
    }

    /**
     * POST /trial/upgrade
     * Body: receipt (file), currency (NGN|GBP), months (3|6|12), student_id (optional)
     *
     * NOTE: months=1 is not a valid paid option — the free trial covers month 1.
     */
    public function upgrade(Request $request)
    {
        $validated = $request->validate([
            'receipt'    => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'currency'   => 'required|in:NGN,GBP',
            'months'     => 'required|integer|in:3,6,12',   // 1 month is the FREE TRIAL
            'student_id' => 'nullable|exists:users,id',
        ]);

        $months    = (int) $validated['months'];
        $tier      = self::TIERS[$months];
        $studentId = $validated['student_id'] ?? auth()->id();
        $student   = User::findOrFail($studentId);
        $amount    = $validated['currency'] === 'NGN' ? $tier['ngn'] : $tier['gbp'];
        $days      = $tier['days'];

        // Auth check — must be the student or their parent
        $requester = auth()->user();
        $isParent  = DB::table('parent_child')
            ->where('parent_id', $requester->id)
            ->where('child_id', $studentId)->exists();

        if ($requester->id !== $student->id && !$isParent) {
            return response()->json(['success' => false, 'message' => 'Not authorised.'], 403);
        }

        // Store receipt
        $file        = $request->file('receipt');
        $receiptPath = $file->storeAs('receipts', time() . '_upgrade_' . $file->getClientOriginalName(), 'public');

        EnrollmentPayment::create([
            'parent_id'           => $isParent ? $requester->id : $student->id,
            'course_id'           => null,
            'amount'              => $amount,
            'currency'            => $validated['currency'],
            'receipt_path'        => $receiptPath,
            'child_name'          => $student->name,
            'status'              => 'temporary_approved',
            'auto_approved'       => true,
            'subscription_months' => $months,
            'includes_maths'      => true,
            'includes_english'    => true,
        ]);

        // Grant premium: extend from current expiry if still active
        $base    = ($student->is_premium
            && $student->premium_expires_at
            && now()->lt($student->premium_expires_at))
            ? \Illuminate\Support\Carbon::parse($student->premium_expires_at)
            : now();
        $expires = $base->addDays($days);

        $student->update(['is_premium' => true, 'premium_expires_at' => $expires]);

        // 🔔 Notify admin that a receipt was uploaded and needs verification
        $this->notifyAdmin($student, $amount, $validated['currency'], $tier, $receiptPath);

        $this->sendConfirmationEmail($student, $amount, $validated['currency'], $tier, $expires, $isParent ? $requester : null);

        return response()->json([
            'success'            => true,
            'message'            => "🎉 {$tier['label']} plan activated! {$days} days of full access.",
            'premium_expires_at' => $expires,
            'days_granted'       => $days,
        ]);
    }

    private function sendConfirmationEmail(User $student, $amount, string $currency, array $tier, $expires, ?User $knownParent): void
    {
        try {
            $parent = $knownParent;
            if (!$parent) {
                $parentId = DB::table('parent_child')->where('child_id', $student->id)->value('parent_id');
                $parent   = $parentId ? User::find($parentId) : null;
            }
            if (!$parent || !$parent->email) return;

            $sym       = $currency === 'NGN' ? '₦' : '£';
            $amtFmt    = $sym . number_format((float) $amount, $currency === 'NGN' ? 0 : 2);
            $expiresOn = \Illuminate\Support\Carbon::parse($expires)->format('d F Y');

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#2A1650;padding:24px;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#FFFF00;'>Learn</span></h1>
              </div>
              <div style='background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;'>
                <p>Dear " . e($parent->name) . ",</p>
                <h2 style='color:#3F2171;font-size:18px;'>✅ Payment received — {$tier['label']} plan activated!</h2>
                <p style='line-height:1.6;'>Thank you! <strong>" . e($student->name) . "</strong> now has full
                access to Maths and English for the next {$tier['days']} days.</p>
                <div style='background:#f3effa;border-radius:12px;padding:16px;margin:16px 0;'>
                  <table width='100%' style='font-size:14px;'>
                    <tr><td style='padding:4px 0;color:#666;'>Student</td><td style='text-align:right;font-weight:bold;'>" . e($student->name) . "</td></tr>
                    <tr><td style='padding:4px 0;color:#666;'>Plan</td><td style='text-align:right;font-weight:bold;'>{$tier['label']} (Maths + English)</td></tr>
                    <tr><td style='padding:4px 0;color:#666;'>Amount paid</td><td style='text-align:right;font-weight:bold;'>{$amtFmt}</td></tr>
                    <tr><td style='padding:4px 0;color:#666;'>Access until</td><td style='text-align:right;font-weight:bold;'>{$expiresOn}</td></tr>
                  </table>
                </div>
                <p style='font-size:13px;color:#666;line-height:1.6;'>Our team verifies all bank transfers within 24 hours. No action needed unless we contact you.</p>
                <p style='margin-top:20px;'>
                  <a href='https://fricalearn.com/login' style='background:#3F2171;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>Open Parent Portal</a>
                </p>
                <p style='color:#999;font-size:12px;margin-top:24px;'>FRICA SOLUTION LIMITED · hello@fricalearn.com · WhatsApp +234 817 448 5504</p>
              </div>
            </div>";

            Mail::html($html, function ($m) use ($parent, $student, $tier) {
                $m->to($parent->email)
                  ->subject("✅ {$student->name}'s FricaLearn {$tier['label']} plan is now active!");
            });
        } catch (\Exception $e) {
            Log::error('Upgrade confirmation email failed: ' . $e->getMessage());
        }
    }

    private function notifyAdmin(\App\Models\User $student, $amount, string $currency, array $tier, string $receiptPath): void
    {
        try {
            $admin = \App\Models\User::where('is_admin', 1)->first();
            if (!$admin || !$admin->email) return;

            $sym    = $currency === 'NGN' ? '₦' : '£';
            $amt    = $sym . number_format((float)$amount, $currency === 'NGN' ? 0 : 2);
            $receiptUrl = asset('storage/' . $receiptPath);

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#2A1650;padding:20px;border-radius:12px 12px 0 0;'>
                <h2 style='color:#FFFF00;margin:0;'>🧾 New Payment Receipt</h2>
              </div>
              <div style='background:#fff;border:1px solid #eee;padding:24px;border-radius:0 0 12px 12px;'>
                <p><strong>Student:</strong> " . e($student->name) . "</p>
                <p><strong>Plan:</strong> {$tier['label']} ({$tier['days']} days)</p>
                <p><strong>Amount:</strong> {$amt}</p>
                <p><strong>Currency:</strong> {$currency}</p>
                <p style='margin-top:20px;'>
                  <a href='" . env('APP_URL') . "/admin' style='background:#3F2171;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;margin-right:10px;'>View Admin Dashboard</a>
                  <a href='{$receiptUrl}' style='background:#1A7A4A;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;'>View Receipt</a>
                </p>
                <p style='color:#999;font-size:12px;margin-top:16px;'>Please verify this payment and approve or reject it in the admin portal.</p>
              </div>
            </div>";

            \Illuminate\Support\Facades\Mail::html($html, function ($m) use ($admin, $student) {
                $m->to($admin->email)
                  ->subject("🧾 Payment Receipt — {$student->name} awaiting verification");
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin receipt notification failed: ' . $e->getMessage());
        }
    }

}
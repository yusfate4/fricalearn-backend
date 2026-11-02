<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutoEnrollmentService;
use Illuminate\Http\Request;
use App\Models\EnrollmentPayment;
use App\Models\CourseEnrollment; 
use App\Models\Course;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Cloudinary\Cloudinary;

// 🚀 Notifications
use App\Notifications\StudentAccountActivated;
use App\Notifications\NewPaymentSubmitted;
use App\Notifications\PaymentReceivedParent;
use App\Notifications\AdminAlertNotification;

class PaymentController extends Controller
{
    /**
     * 💳 Parent: Submit payment
     */
    public function submitPayment(Request $request)
    {
        $request->validate([
            'child_name' => 'required|string',
            'course_id'  => 'required|exists:courses,id',
            'amount'     => 'required|numeric',
            'currency'   => 'required|string',
            'receipt'    => 'required|image|max:5120', 
        ]);

        $parent = $request->user();

        try {
            return DB::transaction(function () use ($request, $parent) {
                $student = User::firstOrCreate(
                    ['name' => trim($request->child_name), 'parent_id' => $parent->id, 'role' => 'student'],
                    [
                        'email' => strtolower(str_replace(' ', '', $request->child_name)) . '_' . rand(100, 999) . '@fricalearn.com',
                        'password' => bcrypt('student123'),
                        'is_admin' => 0,
                        'is_active' => 0, 
                        'email_verified_at' => now(), 
                    ]
                );

                $cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => config('services.cloudinary.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => config('services.cloudinary.api_key') ?? env('CLOUDINARY_API_KEY'),
                        'api_secret' => config('services.cloudinary.api_secret') ?? env('CLOUDINARY_API_SECRET'),
                    ],
                ]);

                $upload = $cloudinary->uploadApi()->upload($request->file('receipt')->getRealPath(), ['folder' => 'fricalearn/payments/receipts']);
                
                $payment = EnrollmentPayment::create([
                    'parent_id'    => $parent->id,
                    'student_id'   => $student->id, 
                    'course_id'    => $request->course_id,
                    'child_name'   => trim($request->child_name),
                    'amount'       => $request->amount,
                    'currency'     => $request->currency,
                    'receipt_path' => $upload['secure_url'],
                    'status'       => 'pending',
                ]);

                // 🚀 Notify Admin via Unified Notification System
                $admin = User::where('is_admin', 1)->first();
                if ($admin) {
                    $admin->notify(new AdminAlertNotification(
                        '💰 New Payment Submitted',
                        "Parent {$parent->name} submitted a payment for child: {$request->child_name}. Amount: {$request->amount} {$request->currency}."
                    ));
                }

                $parent->notify(new PaymentReceivedParent($payment));

                return response()->json(['status' => 'success', 'message' => 'Receipt submitted successfully!'], 201);
            });
        } catch (\Exception $e) {
            Log::error("Payment Submission Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * ✅ Admin: Approve Payment
     */
  /**
     * ✅ Admin: Approve Payment & Sync Parent-Child Relationship
     */
    /**
 * ✅ Admin: Approve Payment & Sync Parent-Child Relationship
 */
public function approvePayment(Request $request, $id)
{
    $user = $request->user();
    // Staff check
    if (!$user || ($user->role !== 'admin' && (int)$user->is_admin !== 1 && $user->role !== 'tutor')) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    $payment = EnrollmentPayment::findOrFail($id);

    // ── Trial upgrade payments (temporary_approved): student already has premium.
    // Approval just marks the bank transfer as verified — no enrollment needed.
    if ($payment->status === 'temporary_approved') {
        try {
            $payment->update(['status' => 'approved', 'approved_at' => now()]);

            // Notify parent: payment confirmed
            $parent = User::find($payment->parent_id);
            if ($parent && $parent->email) {
                $sym    = $payment->currency === 'NGN' ? '₦' : '£';
                $amt    = $sym . number_format((float) $payment->amount, $payment->currency === 'NGN' ? 0 : 2);
                $html = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                  <div style='background:#2A1650;padding:24px;border-radius:16px 16px 0 0;'>
                    <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#FFFF00;'>Learn</span></h1>
                  </div>
                  <div style='background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;'>
                    <p>Dear " . e($parent->name) . ",</p>
                    <h2 style='color:#3F2171;font-size:18px;'>✅ Payment Verified — {$payment->child_name}'s subscription is confirmed!</h2>
                    <p style='line-height:1.6;'>We have verified your bank transfer of <strong>{$amt}</strong> for <strong>" . e($payment->child_name) . "</strong>. Their premium access is fully active and confirmed.</p>
                    <p style='margin-top:20px;'>
                      <a href='https://fricalearn.com/login' style='background:#3F2171;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>Open Parent Portal</a>
                    </p>
                    <p style='color:#999;font-size:12px;margin-top:24px;'>FRICA SOLUTION LIMITED · hello@fricalearn.com</p>
                  </div>
                </div>";
                try {
                    Mail::html($html, function ($m) use ($parent, $payment) {
                        $m->to($parent->email)
                          ->subject("✅ Payment confirmed — {$payment->child_name}'s FricaLearn subscription is active");
                    });
                } catch (\Exception $mailEx) {
                    Log::warning("Approval email failed: " . $mailEx->getMessage());
                }
            }

            return response()->json(['message' => 'Trial upgrade payment verified and approved.']);
        } catch (\Exception $e) {
            Log::error("Trial approval error: " . $e->getMessage());
            return response()->json(['message' => 'Approval failed: ' . $e->getMessage()], 500);
        }
    }

    try {
        return DB::transaction(function () use ($payment) {
            // 1. Find Student and Parent
            $student = User::findOrFail($payment->student_id);
            $parent = User::find($payment->parent_id);

            if (!$parent) {
                throw new \Exception("The parent account associated with this payment no longer exists.");
            }

            // 2. Activate Student and enforce the Parent Link
            $student->update([
                'is_active' => 1,
                'parent_id' => $parent->id
            ]);

            // 3. Link via Pivot Table
            if (method_exists($parent, 'children')) {
                $parent->children()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent']]);
            }

            // 4. Update Payment
            $payment->update([
                'status' => 'approved',
                'approved_at' => now()
            ]);

            // 5. Create Enrollment
            $enrollment = CourseEnrollment::updateOrCreate(
                ['course_id' => $payment->course_id, 'student_id' => $student->id],
                ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->addDays(365)]
            );

            // 6. Setup/Update Profile
            $course = Course::find($payment->course_id);
            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                ['learning_language' => $course ? $course->title : 'General Yoruba', 'rank' => 'Akeko']
            );

            // 🚀 7. AUTO-ENROLL IN MATHS/ENGLISH (NEW!)
            $bonusSubject = null;
            if ($course) {
                $autoEnrollService = app(AutoEnrollmentService::class);
                $bonusSubject = $autoEnrollService->enrollUserInExternalSubject($student, $course);
            }

            // 8. Notify Parent
            try {
                $parent->notify(new \App\Notifications\StudentAccountActivated([
                    'name' => $student->name,
                    'email' => $student->email
                ], $course->title ?? 'General Yoruba'));
            } catch (\Exception $e) {
                Log::error("Activation notification failed: " . $e->getMessage());
            }

            // 🚀 Build response with bonus subject info
            $response = [
                'message' => "Success! Student activated and linked to parent account.",
                'enrollment' => $enrollment
            ];

            if ($bonusSubject) {
                $response['bonus_subject'] = [
                    'name' => $bonusSubject->name,
                    'year_group' => $bonusSubject->year_group,
                    'key_stage' => $bonusSubject->key_stage,
                    'message' => "🎉 Bonus! {$student->name} has been enrolled in {$bonusSubject->name} Year {$bonusSubject->year_group}"
                ];
            }

            return response()->json($response);
        });
    } catch (\Exception $e) {
        Log::error("Approval Error: " . $e->getMessage());
        return response()->json(['message' => 'Action Halted: ' . $e->getMessage()], 500);
    }
}

    /**
     * ❌ Admin: Reject Payment
     *
     * For trial upgrade payments (temporary_approved): revokes premium immediately
     * and emails the parent explaining their transfer could not be verified.
     * For regular pending payments: marks rejected and emails parent.
     */
    public function rejectPayment($id)
    {
        $payment = EnrollmentPayment::findOrFail($id);
        $payment->update(['status' => 'rejected']);

        $parent = User::find($payment->parent_id);

        // If this was a trial upgrade — revoke premium access
        if ($payment->auto_approved) {
            // Find the student by child_name under this parent
            $student = null;
            if ($parent) {
                $student = DB::table('parent_child')
                    ->where('parent_id', $parent->id)
                    ->join('users', 'users.id', '=', 'parent_child.child_id')
                    ->where('users.name', $payment->child_name)
                    ->select('users.id', 'users.trial_ends_at')
                    ->first();
            }
            if ($student) {
                // Restore to trial state (remove premium, keep trial_ends_at if it exists)
                User::where('id', $student->id)->update([
                    'is_premium'         => 0,
                    'premium_expires_at' => null,
                ]);
            }
        }

        // Email parent
        if ($parent && $parent->email) {
            $sym  = $payment->currency === 'NGN' ? '₦' : '£';
            $amt  = $sym . number_format((float) $payment->amount, $payment->currency === 'NGN' ? 0 : 2);
            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
              <div style='background:#2A1650;padding:24px;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#FFFF00;'>Learn</span></h1>
              </div>
              <div style='background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;'>
                <p>Dear " . e($parent->name) . ",</p>
                <h2 style='color:#c0392b;font-size:18px;'>⚠️ Payment not verified — action needed</h2>
                <p style='line-height:1.6;'>We were unable to verify your bank transfer of <strong>{$amt}</strong> for <strong>" . e($payment->child_name) . "</strong>.</p>
                <p style='line-height:1.6;'>This could mean:</p>
                <ul style='color:#555;line-height:1.8;'>
                  <li>The transfer amount did not match</li>
                  <li>The child's name was not used as the payment reference</li>
                  <li>The receipt image was unclear or incomplete</li>
                </ul>
                <p style='line-height:1.6;'>Please contact us on WhatsApp at <strong>+234 817 448 5504</strong> or reply to this email and we will resolve it promptly.</p>
                <p style='margin-top:20px;'>
                  <a href='https://fricalearn.com/login' style='background:#3F2171;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>Open Parent Portal</a>
                </p>
                <p style='color:#999;font-size:12px;margin-top:24px;'>FRICA SOLUTION LIMITED · hello@fricalearn.com</p>
              </div>
            </div>";
            try {
                Mail::html($html, function ($m) use ($parent, $payment) {
                    $m->to($parent->email)
                      ->subject("⚠️ Payment not verified — {$payment->child_name}'s FricaLearn subscription");
                });
            } catch (\Exception $e) {
                Log::warning("Rejection email failed: " . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Payment rejected and parent notified.']);
    }

    /**
     * 📊 Fetch Pending Payments
     *
     * FIX: also includes 'temporary_approved' (trial upgrades via TrialController::upgrade).
     * These payments already granted the student access automatically, but still need admin
     * to verify the bank transfer. They show as "Needs Verification" in the admin UI.
     */
    public function getPendingPayments()
    {
        return response()->json(
            EnrollmentPayment::with(['parent:id,name,email', 'course:id,title'])
                ->whereIn('status', ['pending', 'temporary_approved'])
                ->whereNotNull('parent_id')
                ->latest()
                ->get()
        );
    }

    /**
     * 📜 Fetch Payment History
     *
     * FIX: also includes 'temporary_approved' so trial upgrade payments appear in history.
     * Previously, all payments made via TrialController::upgrade() were invisible here
     * because they store status='temporary_approved', not 'approved' or 'rejected'.
     */
    public function getPaymentHistory()
    {
        return response()->json(
            EnrollmentPayment::with(['parent:id,name', 'course:id,title'])
                ->whereIn('status', ['approved', 'rejected', 'temporary_approved'])
                ->latest()
                ->get()
        );
    }
}
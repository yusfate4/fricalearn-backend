<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function approvePayment(Request $request, $id)
    {
        $user = $request->user();
        // Staff check
        if (!$user || ($user->role !== 'admin' && (int)$user->is_admin !== 1 && $user->role !== 'tutor')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $payment = EnrollmentPayment::findOrFail($id);

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
                    'parent_id' => $parent->id // 🚀 Ensures Parent Portal query finds them
                ]);

                // 3. Link via Pivot Table (The "Parent Portal" connector)
                if (method_exists($parent, 'children')) {
                    $parent->children()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent']]);
                }

                // 4. Update Payment
                $payment->update([
                    'status' => 'approved', 
                    'approved_at' => now()
                ]);

                // 5. Create Enrollment
                CourseEnrollment::updateOrCreate(
                    ['course_id' => $payment->course_id, 'student_id' => $student->id],
                    ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->addDays(365)]
                );

                // 6. Setup/Update Profile
                $course = Course::find($payment->course_id);
                StudentProfile::updateOrCreate(
                    ['user_id' => $student->id],
                    ['learning_language' => $course ? $course->title : 'General Yoruba', 'rank' => 'Akeko']
                );

                // 7. Notify Parent
                try {
                    $parent->notify(new \App\Notifications\StudentAccountActivated([
                        'name' => $student->name, 
                        'email' => $student->email
                    ], $course->title ?? 'General Yoruba'));
                } catch (\Exception $e) {
                    Log::error("Activation notification failed: " . $e->getMessage());
                }

                return response()->json(['message' => "Success! Student activated and linked to parent account."]);
            });
        } catch (\Exception $e) {
            Log::error("Approval Error: " . $e->getMessage());
            return response()->json(['message' => 'Action Halted: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ❌ Admin: Reject Payment
     */
    public function rejectPayment($id)
    {
        $payment = EnrollmentPayment::findOrFail($id);
        $payment->update(['status' => 'rejected']);
        return response()->json(['message' => 'Payment rejected.']);
    }

    /**
     * 📊 Fetch Pending Payments
     */
    public function getPendingPayments()
    {
        return response()->json(
            EnrollmentPayment::with(['parent:id,name,email', 'course:id,title'])
                ->where('status', 'pending')
                ->whereNotNull('parent_id')
                ->latest()
                ->get()
        );
    }

    /**
     * 📜 Fetch Payment History
     */
    public function getPaymentHistory()
    {
        return response()->json(
            EnrollmentPayment::with(['parent:id,name', 'course:id,title'])
                ->whereIn('status', ['approved', 'rejected'])
                ->latest()
                ->get()
        );
    }
}
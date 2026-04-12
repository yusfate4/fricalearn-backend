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
use Cloudinary\Cloudinary;

// 🚀 NOTIFICATIONS
use App\Notifications\StudentAccountActivated;
use App\Notifications\NewPaymentSubmitted;
use App\Notifications\PaymentReceivedParent; // 👈 Added this

class PaymentController extends Controller
{
    /**
     * 💳 Parent: Submit payment and notify Admin & Parent
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
                
                // 🚀 1. Create/Find Student (Inactive)
                $student = User::firstOrCreate(
                    [
                        'name' => trim($request->child_name), 
                        'parent_id' => $parent->id,
                        'role' => 'student'
                    ],
                    [
                        'email' => strtolower(str_replace(' ', '', $request->child_name)) . '_' . rand(100, 999) . '@fricalearn.com',
                        'password' => bcrypt('student123'),
                        'is_admin' => 0,
                        'is_active' => 0, 
                    ]
                );

                // ☁️ 2. Cloudinary Upload
                try {
                    $cloudinary = new Cloudinary([
                        'cloud' => [
                            'cloud_name' => config('services.cloudinary.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME'),
                            'api_key'    => config('services.cloudinary.api_key') ?? env('CLOUDINARY_API_KEY'),
                            'api_secret' => config('services.cloudinary.api_secret') ?? env('CLOUDINARY_API_SECRET'),
                        ],
                    ]);

                    $upload = $cloudinary->uploadApi()->upload(
                        $request->file('receipt')->getRealPath(),
                        ['folder' => 'fricalearn/payments/receipts']
                    );
                    $receiptUrl = $upload['secure_url'];
                } catch (\Exception $e) {
                    Log::error("Cloudinary Upload Error: " . $e->getMessage());
                    throw new \Exception("Receipt upload failed. Check your connection.");
                }

                // 📄 3. Create Payment Record
                $payment = EnrollmentPayment::create([
                    'parent_id'    => $parent->id,
                    'student_id'   => $student->id, 
                    'course_id'    => $request->course_id,
                    'child_name'   => trim($request->child_name),
                    'amount'       => $request->amount,
                    'currency'     => $request->currency,
                    'receipt_path' => $receiptUrl,
                    'status'       => 'pending',
                ]);

                // 🔔 4a. NOTIFY ADMINS (Verification Required)
                $admins = User::where('role', 'admin')->orWhere('is_admin', 1)->get();
                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new NewPaymentSubmitted($payment));
                    } catch (\Exception $e) {
                        Log::warning("Admin email failed but transaction continued: " . $e->getMessage());
                    }
                }

                // 🔔 4b. NOTIFY PARENT (Peace of Mind)
                try {
                    $parent->notify(new PaymentReceivedParent($payment));
                } catch (\Exception $e) {
                    Log::warning("Parent email failed but transaction continued: " . $e->getMessage());
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Receipt submitted! You and the Admin have been notified.',
                    'payment_id' => $payment->id
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Payment Submission 500: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Admin: Approve Payment, Activate Account & Notify Parent
     */
    public function approvePayment(Request $request, $id)
{
    $user = $request->user();
    if (!$user || ($user->role !== 'admin' && (int)$user->is_admin !== 1 && $user->role !== 'tutor')) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    $payment = EnrollmentPayment::findOrFail($id);

    try {
        return DB::transaction(function () use ($payment) {
            if ($payment->status === 'approved') {
                return response()->json(['message' => 'Already approved.'], 400);
            }

            // 1. Activate Student
            $student = User::find($payment->student_id);
            if ($student) {
                $student->update(['is_active' => 1]);
            }

            // 2. Link Relationship (Wrapped in try-catch to prevent 500 if relationship fails)
            try {
                $parent = User::find($payment->parent_id);
                if ($parent && $student && method_exists($parent, 'children')) {
                    $parent->children()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent']]);
                }
            } catch (\Exception $e) {
                Log::error("Relationship Sync Failed: " . $e->getMessage());
            }

            // 3. Update Status
            $payment->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            // 4. Enrollment & Profile
            CourseEnrollment::updateOrCreate(
                ['course_id' => $payment->course_id, 'student_id' => $student->id],
                ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->addDays(365)]
            );

            $course = Course::find($payment->course_id);
            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                ['learning_language' => $course->title ?? 'Yoruba', 'rank' => 'Akeko']
            );

            // 🚀 5. NOTIFY PARENT (The most common cause of 500 errors)
            // We use a separate try-catch so an email failure doesn't roll back the activation
            try {
                if ($parent && $student) {
                    $parent->notify(new StudentAccountActivated([
                        'name'  => $student->name,
                        'email' => $student->email
                    ], $course->title ?? 'General Yoruba'));
                }
            } catch (\Exception $e) {
                Log::error("Activation Email Failed: " . $e->getMessage());
                // We DON'T throw the error here, so the student stays activated!
            }

            return response()->json(['message' => "Success! Student activated."]);
        });
    } catch (\Exception $e) {
        Log::error("Approval 500 Error: " . $e->getMessage());
        return response()->json(['message' => 'Internal Error: ' . $e->getMessage()], 500);
    }
}
    public function getPendingPayments()
    {
        return response()->json(
            EnrollmentPayment::with(['parent:id,name,email', 'course:id,title'])
                ->where('status', 'pending')->latest()->get()
        );
    }

    public function getPaymentHistory()
    {
        return response()->json(
            EnrollmentPayment::with(['parent:id,name', 'course:id,title'])
                ->whereIn('status', ['approved', 'rejected'])->latest()->get()
        );
    }

    public function rejectPayment($id)
    {
        $payment = EnrollmentPayment::findOrFail($id);
        $payment->update(['status' => 'rejected']);
        return response()->json(['message' => 'Payment rejected.']);
    }
}
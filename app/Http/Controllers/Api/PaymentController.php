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

class PaymentController extends Controller
{
    /**
     * 💳 Parent: Submit payment and notify Admin
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
                // Using a safer email generation to avoid duplicates during 500 retries
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

                // ☁️ 2. Cloudinary Upload (Initialized inside to ensure envs are fresh)
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
                    throw new \Exception("Receipt upload failed. Please check file size or Cloudinary config.");
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

                // 🔔 4. Notify Admins
                $admins = User::where('role', 'admin')->orWhere('is_admin', 1)->get();
                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new NewPaymentSubmitted($payment));
                    } catch (\Exception $e) {
                        Log::warning("Admin notification failed: " . $e->getMessage());
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Receipt submitted successfully! Oluko has notified the Admin for verification.',
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
     * ✅ Admin: Approve Payment
     */
    public function approvePayment(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || ($user->role !== 'admin' && (int)$user->is_admin !== 1 && $user->role !== 'tutor')) {
            return response()->json(['message' => 'Unauthorized. Staff access only.'], 403);
        }

        $payment = EnrollmentPayment::findOrFail($id);

        return DB::transaction(function () use ($payment) {
            if ($payment->status === 'approved') {
                return response()->json(['message' => 'Already approved.'], 400);
            }

            // 1. Activate Student
            $student = User::find($payment->student_id);
            if ($student) {
                $student->update(['is_active' => 1]);
            }

            // 2. Link Relationship
            $parent = User::find($payment->parent_id);
            if ($parent && $student) {
                $parent->children()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent']]);
            }

            // 3. Update Payment Status
            $payment->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            // 4. Create Enrollment
            CourseEnrollment::updateOrCreate(
                ['course_id' => $payment->course_id, 'student_id' => $student->id],
                ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->addDays(365)]
            );

            // 5. Setup Profile
            $course = Course::find($payment->course_id);
            $trackName = $course ? ($course->title) : 'General'; 

            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                ['learning_language' => $trackName, 'rank' => 'Akeko']
            );

            // 6. Notify Parent
            if ($parent && $student) {
                try {
                    $parent->notify(new StudentAccountActivated([
                        'name'  => $student->name,
                        'email' => $student->email
                    ], $trackName));
                } catch (\Exception $e) {
                    Log::error("Parent activation notification failed: " . $e->getMessage());
                }
            }

            return response()->json(['message' => "Success! Student activated and parent notified."]);
        });
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
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\EnrollmentPayment;
use App\Models\CourseEnrollment; 
use App\Models\Course;
use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;
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
        $validated = $request->validate([
            'child_name' => 'required|string',
            'course_id'  => 'required|exists:courses,id',
            'amount'     => 'required|numeric',
            'currency'   => 'required|string',
            'receipt'    => 'required|image|max:5120', 
        ]);

        $parent = $request->user();

        // Initialize Cloudinary
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        return DB::transaction(function () use ($validated, $parent, $request, $cloudinary) {
            
            // 🚀 1. Create/Find Student (Inactive)
            $student = User::firstOrCreate(
                [
                    'name' => trim($validated['child_name']), 
                    'parent_id' => $parent->id,
                    'role' => 'student'
                ],
                [
                    'email' => strtolower(str_replace(' ', '', $validated['child_name'])) . '_' . uniqid() . '@fricalearn.com',
                    'password' => bcrypt('student123'),
                    'is_admin' => 0,
                    'is_active' => 0, 
                ]
            );

            // ☁️ 2. Upload Receipt
            try {
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('receipt')->getRealPath(),
                    ['folder' => 'fricalearn/payments/receipts']
                );
                $receiptUrl = $upload['secure_url'];
            } catch (\Exception $e) {
                throw new \Exception("Receipt upload failed: " . $e->getMessage());
            }

            // 📄 3. Create Payment Record
            $payment = EnrollmentPayment::create([
                'parent_id'    => $parent->id,
                'student_id'   => $student->id, 
                'course_id'    => $validated['course_id'],
                'child_name'   => trim($validated['child_name']),
                'amount'       => $validated['amount'],
                'currency'     => $validated['currency'],
                'receipt_path' => $receiptUrl,
                'status'       => 'pending',
            ]);

            // 🔔 4. NOTIFY ADMINS IMMEDIATELY
            // Find all users with staff privileges to review the payment
            $admins = User::where('role', 'admin')->orWhere('is_admin', 1)->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewPaymentSubmitted($payment));
            }

            return response()->json([
                'message' => 'Receipt submitted successfully! Oluko has notified the Admin for verification.',
                'payment_id' => $payment->id
            ], 201);
        });
    }

    /**
     * ✅ Admin: Approve Payment, Activate Account & Notify Parent
     */
    public function approvePayment(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized. Staff access only.'], 403);
        }

        $payment = EnrollmentPayment::find($id);

        if (!$payment) {
            return response()->json(['message' => "Payment record #{$id} not found."], 404);
        }

        return DB::transaction(function () use ($payment) {
            if ($payment->status === 'approved') {
                return response()->json(['message' => 'Already approved.'], 400);
            }

            // 1. Activate Student
            $student = User::find($payment->student_id);
            if ($student) {
                $student->update(['is_active' => 1]);
            }

            // 2. Link Parent/Child Relationship
            $parent = User::find($payment->parent_id);
            if ($parent && $student) {
                $parent->children()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent']]);
            }

            // 3. Update Status
            $payment->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            // 4. Enrollment
            CourseEnrollment::updateOrCreate(
                ['course_id' => $payment->course_id, 'student_id' => $student->id],
                ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->addDays(365)]
            );

            // 5. Profile Setup
            $course = Course::find($payment->course_id);
            $trackName = $course ? ($course->subject ?? $course->title) : 'General'; 

            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                ['learning_language' => $trackName, 'rank' => 'Akeko', 'total_points' => 0]
            );

            // 🚀 6. NOTIFY PARENT WITH LOGIN DETAILS
            if ($parent && $student) {
                $parent->notify(new StudentAccountActivated([
                    'name'  => $student->name,
                    'email' => $student->email
                ], $trackName));
            }

            return response()->json(['message' => "Success! Student activated and parent notified."]);
        });
    }

    /**
     * 👑 Admin View Methods
     */
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
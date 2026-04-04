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
// 🚀 Use the pure Cloudinary SDK for manual injection
use Cloudinary\Cloudinary;

class PaymentController extends Controller
{
    /**
     * 💳 Parent: Submit payment and auto-initialize student account
     * Maps to: /api/parent/submit-payment AND /api/parent/payments/submit
     */
    public function submitPayment(Request $request)
    {
        $validated = $request->validate([
            'child_name' => 'required|string',
            'course_id'  => 'required|exists:courses,id',
            'amount'     => 'required|numeric',
            'currency'   => 'required|string',
            'receipt'    => 'required|image|max:5120', // 5MB Max
        ]);

        $parent = $request->user();

        // ☁️ Initialize Cloudinary Manual Instance
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        return DB::transaction(function () use ($validated, $parent, $request, $cloudinary) {
            
            // 🚀 STEP 1: AUTO-INITIALIZE STUDENT
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
                ]
            );

            // 🚀 STEP 2: UPLOAD RECEIPT TO CLOUDINARY
            try {
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('receipt')->getRealPath(),
                    ['folder' => 'fricalearn/payments/receipts']
                );
                $receiptUrl = $upload['secure_url'];
            } catch (\Exception $e) {
                throw new \Exception("Receipt upload failed: " . $e->getMessage());
            }

            // 🚀 STEP 3: CREATE PAYMENT RECORD
            $payment = EnrollmentPayment::create([
                'parent_id'    => $parent->id,
                'user_id'      => $student->id, 
                'course_id'    => $validated['course_id'],
                'child_name'   => trim($validated['child_name']),
                'amount'       => $validated['amount'],
                'currency'     => $validated['currency'],
                'receipt_path' => $receiptUrl, // Now storing the Cloudinary URL
                'status'       => 'pending',
            ]);

            return response()->json([
                'message' => 'Receipt submitted! Account for ' . $student->name . ' is awaiting verification.',
                'payment_id' => $payment->id
            ], 201);
        });
    }

    /**
     * ✅ Admin: Approve Payment & Activate Account
     */
    public function approvePayment(Request $request, $id)
    {
        $payment = EnrollmentPayment::find($id);

        if (!$payment) {
            return response()->json(['message' => "Payment record #{$id} not found."], 404);
        }

        return DB::transaction(function () use ($payment) {
            if ($payment->status === 'approved') {
                return response()->json(['message' => 'Already approved.'], 400);
            }

            // 1. FIND THE STUDENT (Ensure student exists or recreate if deleted)
            $student = User::where('role', 'student')
                ->where('id', $payment->user_id)
                ->first();

            if (!$student) {
                $student = User::create([
                    'name' => trim($payment->child_name),
                    'email' => strtolower(str_replace(' ', '', $payment->child_name)) . '_' . rand(100, 999) . '@fricalearn.com',
                    'password' => bcrypt('student123'),
                    'role' => 'student',
                    'parent_id' => $payment->parent_id,
                ]);
            }

            // 2. SYNC RELATIONSHIPS
            $parent = User::find($payment->parent_id);
            if ($parent) {
                // If using a many-to-many child pivot
                if (method_exists($parent, 'children')) {
                    $parent->children()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent']]);
                }
            }

            // 3. UPDATE PAYMENT STATUS
            $payment->update([
                'user_id' => $student->id,
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            // 4. ACTIVATE COURSE ENROLLMENT
            CourseEnrollment::updateOrCreate(
                ['course_id' => $payment->course_id, 'student_id' => $student->id],
                [
                    'status' => 'active', 
                    'enrolled_at' => now(), 
                    'expires_at' => now()->addDays(365) 
                ]
            );

            // 🚀 5. UPDATE STUDENT PROFILE TRACK
            $course = Course::find($payment->course_id);
            $trackName = $course ? ($course->subject ?? $course->title) : 'General'; 

            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                [
                    'learning_language' => $trackName,
                    'rank' => 'Akeko',
                    'total_points' => 0,
                    'total_coins' => 0
                ]
            );

            return response()->json(['message' => "Success! {$student->name} is now active on the {$trackName} track."]);
        });
    }

    /**
     * 👑 Admin: Fetch all pending payments
     */
    public function getPendingPayments()
    {
        return response()->json(
            EnrollmentPayment::with(['parent:id,name,email', 'course:id,title'])
                ->where('status', 'pending')
                ->latest()
                ->get()
        );
    }

    /**
     * 📜 Admin: History
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

    /**
     * ❌ Admin: Reject Payment
     */
    public function rejectPayment($id)
    {
        $payment = EnrollmentPayment::findOrFail($id);
        // Note: We don't delete Cloudinary assets here automatically to keep a record of why it was rejected
        $payment->update(['status' => 'rejected']);
        return response()->json(['message' => 'Payment rejected.']);
    }
}
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

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);

        return DB::transaction(function () use ($validated, $parent, $request, $cloudinary) {
            
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

            try {
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('receipt')->getRealPath(),
                    ['folder' => 'fricalearn/payments/receipts']
                );
                $receiptUrl = $upload['secure_url'];
            } catch (\Exception $e) {
                throw new \Exception("Receipt upload failed: " . $e->getMessage());
            }

            $payment = EnrollmentPayment::create([
                'parent_id'    => $parent->id,
                'user_id'      => $student->id, 
                'course_id'    => $validated['course_id'],
                'child_name'   => trim($validated['child_name']),
                'amount'       => $validated['amount'],
                'currency'     => $validated['currency'],
                'receipt_path' => $receiptUrl, // Correctly storing full Cloudinary URL
                'status'       => 'pending',
            ]);

            return response()->json([
                'message' => 'Receipt submitted successfully!',
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

            $student = User::where('role', 'student')->where('id', $payment->user_id)->first();

            if (!$student) {
                $student = User::create([
                    'name' => trim($payment->child_name),
                    'email' => strtolower(str_replace(' ', '', $payment->child_name)) . '_' . rand(100, 999) . '@fricalearn.com',
                    'password' => bcrypt('student123'),
                    'role' => 'student',
                    'parent_id' => $payment->parent_id,
                ]);
            }

            $payment->update([
                'user_id' => $student->id,
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            CourseEnrollment::updateOrCreate(
                ['course_id' => $payment->course_id, 'student_id' => $student->id],
                [
                    'status' => 'active', 
                    'enrolled_at' => now(), 
                    'expires_at' => now()->addDays(365) 
                ]
            );

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

            return response()->json(['message' => "Success! Account activated."]);
        });
    }

    /**
     * 👑 Admin: Fetch all pending payments
     * THE FIX: Ensure receipt_path doesn't get double-prefixed on the frontend
     */
    public function getPendingPayments()
    {
        $payments = EnrollmentPayment::with(['parent:id,name,email', 'course:id,title'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        // 🚀 Clean the URLs before sending to Admin panel
        $payments->transform(function($payment) {
            if ($payment->receipt_path && !str_starts_with($payment->receipt_path, 'http')) {
                $payment->receipt_path = asset('storage/' . $payment->receipt_path);
            }
            return $payment;
        });

        return response()->json($payments);
    }

    /**
     * 📜 Admin: History
     */
    public function getPaymentHistory()
    {
        $payments = EnrollmentPayment::with(['parent:id,name', 'course:id,title'])
            ->whereIn('status', ['approved', 'rejected'])
            ->latest()
            ->get();

        $payments->transform(function($payment) {
            if ($payment->receipt_path && !str_starts_with($payment->receipt_path, 'http')) {
                $payment->receipt_path = asset('storage/' . $payment->receipt_path);
            }
            return $payment;
        });

        return response()->json($payments);
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
}
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
use Illuminate\Support\Facades\Mail; // 🚀 Added for Admin Alerts
use Cloudinary\Cloudinary;

// 🚀 NOTIFICATIONS
use App\Notifications\StudentAccountActivated;
use App\Notifications\NewPaymentSubmitted;
use App\Notifications\PaymentReceivedParent;

class PaymentController extends Controller
{
    /**
     * 💳 Parent: Submit payment, notify Admin & Parent
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
                        'email_verified_at' => now(), // Auto-verify linked student
                    ]
                );

                // Cloudinary Upload
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
                    throw new \Exception("Receipt upload failed.");
                }

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

                // 🔔 4a. NOTIFY ADMINS (Dashboard Notification + Email Alert)
                $admins = User::where('role', 'admin')->orWhere('is_admin', 1)->get();
                foreach ($admins as $admin) {
                    $admin->notify(new NewPaymentSubmitted($payment));
                }

                // 🚀 ADDED: Direct Email Alert to hello@fricalearn.com
                Mail::raw("New payment submitted by Parent: {$parent->name} for child: {$request->child_name}. Amount: {$request->amount} {$request->currency}. Please check the Admin Dashboard to verify.", function ($message) {
                    $message->to('hello@fricalearn.com')
                            ->subject('💰 New Payment Pending Verification - FricaLearn');
                });

                // 🔔 4b. NOTIFY PARENT
                $parent->notify(new PaymentReceivedParent($payment));

                return response()->json([
                    'status' => 'success',
                    'message' => 'Receipt submitted! Oluko has notified the Admin for verification.',
                    'payment_id' => $payment->id
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Payment Submission 500: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Action Halted: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ✅ Admin: Approve Payment
     */
    public function approvePayment(Request $request, $id)
    {
        // ... (Keep your existing approval logic here) ...
        // Ensure you have similar Mail::raw alert here if you want to notify admins upon approval
    }

    // ... (Keep existing history/rejection methods) ...
}
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

        return DB::transaction(function () use ($validated, $parent, $request) {
            
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

            // 🚀 STEP 2: SAVE RECEIPT
            $path = $request->file('receipt')->store('receipts', 'public');

            // 🚀 STEP 3: CREATE PAYMENT
            $payment = EnrollmentPayment::create([
                'parent_id'    => $parent->id,
                'user_id'      => $student->id, 
                'course_id'    => $validated['course_id'],
                'child_name'   => trim($validated['child_name']),
                'amount'       => $validated['amount'],
                'currency'     => $validated['currency'],
                'receipt_path' => $path,
                'status'       => 'pending',
            ]);

            return response()->json([
                'message' => 'Receipt submitted! Account for ' . $student->name . ' is awaiting verification.',
                'payment_id' => $payment->id
            ]);
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

            // 1. FIND OR CREATE THE STUDENT
            $student = User::where('role', 'student')
                ->where(function($query) use ($payment) {
                    $query->where('id', $payment->user_id)
                          ->orWhere('name', 'LIKE', trim($payment->child_name));
                })->first();

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
            $student->update(['parent_id' => $payment->parent_id]);
            $parent = User::find($payment->parent_id);
            if ($parent) {
                $parent->children()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent']]);
            }

            // 3. UPDATE PAYMENT STATUS
            $payment->update([
                'user_id' => $student->id,
                'status' => 'approved',
                'approved_at' => now(),
                'expires_at' => now()->addDays(365), // 🚀 Set to 1 year
            ]);

            // 4. ACTIVATE COURSE ENROLLMENT
            CourseEnrollment::updateOrCreate(
                ['course_id' => $payment->course_id, 'student_id' => $student->id],
                [
                    'status' => 'active', 
                    'enrolled_at' => now(), 
                    'expires_at' => now()->addDays(365) // 🚀 Set to 1 year
                ]
            );

            // 🚀 5. UPDATE STUDENT PROFILE TRACK
            // This ensures the dashboard badge shows the right course, not "Yoruba"
            $course = Course::find($payment->course_id);
            $trackName = $course ? ($course->subject ?? $course->title) : 'Yoruba'; 

            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                [
                    'learning_language' => $trackName, // 👈 Dynamically set from the course
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
        
        if ($payment->receipt_path) {
            $cleanPath = str_replace(['/storage/', 'storage/'], '', $payment->receipt_path);
            Storage::disk('public')->delete($cleanPath);
        }

        $payment->update(['status' => 'rejected']);
        return response()->json(['message' => 'Payment rejected.']);
    }
}
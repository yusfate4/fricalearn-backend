<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\EnrollmentPayment;
use App\Models\StudentProfile;
use App\Services\AutoEnrollmentService;

class OnboardingController extends Controller
{
    /**
     * STEP 1: Get available courses for onboarding
     * Returns: Maths, English, Yoruba, Hausa, Igbo with pricing and grade ranges
     */
    public function getCourses()
    {
        $courses = [
            [
                'id' => 'maths',
                'name' => 'Mathematics (UK Curriculum)',
                'description' => 'Master essential maths skills aligned with UK Key Stages 1-4',
                'price_ngn' => 20000,
                'price_gbp' => 13.33,
                'type' => 'paid',
                'grades' => range(1, 10),
                'icon' => '🔢'
            ],
            [
                'id' => 'english',
                'name' => 'English (UK Curriculum)',
                'description' => 'Develop reading, writing, and comprehension skills',
                'price_ngn' => 20000,
                'price_gbp' => 13.33,
                'type' => 'paid',
                'grades' => range(1, 10),
                'icon' => '📚'
            ],
            [
                'id' => 'yoruba',
                'name' => 'Yoruba Language',
                'description' => 'Connect with Yoruba heritage through language and culture',
                'price_ngn' => 0,
                'price_gbp' => 0,
                'type' => 'free',
                'original_price_ngn' => 20000,
                'original_price_gbp' => 13.33,
                'scholarship' => true,
                'icon' => '🇳🇬'
            ],
            [
                'id' => 'hausa',
                'name' => 'Hausa Language',
                'description' => 'Learn Hausa language and cultural traditions',
                'price_ngn' => 0,
                'price_gbp' => 0,
                'type' => 'free',
                'original_price_ngn' => 20000,
                'original_price_gbp' => 13.33,
                'scholarship' => true,
                'icon' => '🇳🇬'
            ],
            [
                'id' => 'igbo',
                'name' => 'Igbo Language',
                'description' => 'Explore Igbo language and heritage',
                'price_ngn' => 0,
                'price_gbp' => 0,
                'type' => 'free',
                'original_price_ngn' => 20000,
                'original_price_gbp' => 13.33,
                'scholarship' => true,
                'icon' => '🇳🇬'
            ]
        ];

        return response()->json([
            'success' => true,
            'courses' => $courses
        ]);
    }

    /**
     * STEP 2: Calculate pricing based on selected courses
     */
    public function calculatePricing(Request $request)
    {
        $validated = $request->validate([
            'selected_courses' => 'required|array|min:1',
            'currency' => 'required|in:NGN,GBP'
        ]);

        $prices = [
            'maths' => ['NGN' => 20000, 'GBP' => 13.33],
            'english' => ['NGN' => 20000, 'GBP' => 13.33],
            'yoruba' => ['NGN' => 0, 'GBP' => 0],
            'hausa' => ['NGN' => 0, 'GBP' => 0],
            'igbo' => ['NGN' => 0, 'GBP' => 0],
        ];

        $total = 0;
        $breakdown = [];

        foreach ($validated['selected_courses'] as $course) {
            if (isset($prices[$course])) {
                $amount = $prices[$course][$validated['currency']];
                $total += $amount;
                
                $breakdown[] = [
                    'course' => $course,
                    'name' => ucfirst($course),
                    'amount' => $amount,
                    'is_free' => $amount == 0,
                    'currency' => $validated['currency']
                ];
            }
        }

        return response()->json([
            'success' => true,
            'currency' => $validated['currency'],
            'breakdown' => $breakdown,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total
        ]);
    }

    /**
     * STEP 3: Get bank account details for payment
     */
    public function getBankDetails()
    {
        return response()->json([
            'success' => true,
            'bank_accounts' => [
                'ngn' => [
                    'currency' => 'NGN',
                    'bank_name' => 'PROVIDUS BANK',
                    'account_number' => '1309393680',
                    'account_name' => 'FRICA SOLUTION LIMITED',
                    'flag' => '🇳🇬'
                ],
                'gbp' => [
                    'currency' => 'GBP',
                    'bank_name' => 'Monzo/Revolut',
                    'account_number' => '012345678',
                    'account_name' => 'FRICA SOLUTION LIMITED',
                    'flag' => '🇬🇧'
                ]
            ],
            'payment_instructions' => [
                'Use the child\'s name as payment reference',
                'Upload clear photo or PDF of payment receipt',
                'Access is granted immediately upon submission',
                'Admin will verify payment within 24 hours'
            ]
        ]);
    }

    /**
     * STEP 4: Submit complete onboarding with payment
     * Creates child user, uploads receipt, auto-approves, and enrolls in courses
     */
    public function submitOnboarding(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'required|exists:users,id',
            'child_name' => 'required|string|max:255',
            'child_email' => 'required|email|unique:users,email',
            'child_password' => 'required|string|min:6',
            'selected_courses' => 'required|array|min:1',
            'selected_courses.*' => 'in:maths,english,yoruba,hausa,igbo',
            'maths_grade' => 'nullable|integer|between:1,10',
            'english_grade' => 'nullable|integer|between:1,10',
            'currency' => 'required|in:NGN,GBP',
            'total_amount' => 'required|numeric|min:0',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        // Validate that grades are provided if courses are selected
        if (in_array('maths', $validated['selected_courses']) && !$validated['maths_grade']) {
            return response()->json([
                'success' => false,
                'message' => 'Maths grade is required when selecting Mathematics'
            ], 422);
        }

        if (in_array('english', $validated['selected_courses']) && !$validated['english_grade']) {
            return response()->json([
                'success' => false,
                'message' => 'English grade is required when selecting English'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 1. Create child user account
            $child = User::create([
                'name' => $validated['child_name'],
                'email' => $validated['child_email'],
                'password' => Hash::make($validated['child_password']),
                'role' => 'student',
                'selected_courses' => json_encode($validated['selected_courses']),
                'maths_grade' => $validated['maths_grade'] ?? null,
                'english_grade' => $validated['english_grade'] ?? null,
                'onboarding_completed' => true,
                'email_verified_at' => now(), // Auto-verify email
            ]);

            // 2. Create student profile with week tracking
            StudentProfile::create([
                'user_id' => $child->id,
                'learning_language' => $this->determineLearningLanguage($validated['selected_courses']),
                'current_week' => 1,
                'week_unlocked_at' => json_encode(['1' => now()->toISOString()]),
                'lagging_topics' => json_encode([]),
                'strong_topics' => json_encode([]),
            ]);

            // 3. Link parent to child
            DB::table('parent_child')->insert([
                'parent_id' => $validated['parent_id'],
                'child_id' => $child->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Upload payment receipt
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store('receipts', 'public');
            }

            // 5. Create payment record with AUTO-APPROVAL
            $payment = EnrollmentPayment::create([
                'user_id' => $child->id,
                'parent_id' => $validated['parent_id'],
                'amount' => $validated['total_amount'],
                'currency' => $validated['currency'],
                'receipt_path' => $receiptPath,
                'status' => 'temporary_approved', // 🚀 Auto-approved for immediate access
                'auto_approved' => true,
                'admin_verified' => false,
                'includes_maths' => in_array('maths', $validated['selected_courses']),
                'includes_english' => in_array('english', $validated['selected_courses']),
                'includes_yoruba' => in_array('yoruba', $validated['selected_courses']),
                'includes_hausa' => in_array('hausa', $validated['selected_courses']),
                'includes_igbo' => in_array('igbo', $validated['selected_courses']),
                'maths_grade' => $validated['maths_grade'] ?? null,
                'english_grade' => $validated['english_grade'] ?? null,
            ]);

            // 6. Trigger auto-enrollment in selected courses
            app(AutoEnrollmentService::class)->enrollStudent($payment);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Onboarding successful! Your child now has access to all selected courses.',
                'data' => [
                    'child' => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'email' => $child->email,
                    ],
                    'payment' => [
                        'id' => $payment->id,
                        'status' => 'temporary_approved',
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                    ],
                    'courses_enrolled' => $validated['selected_courses'],
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Onboarding failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine primary learning language based on selections
     */
    private function determineLearningLanguage(array $courses)
    {
        if (in_array('yoruba', $courses)) return 'Yoruba';
        if (in_array('hausa', $courses)) return 'Hausa';
        if (in_array('igbo', $courses)) return 'Igbo';
        return 'English';
    }
}

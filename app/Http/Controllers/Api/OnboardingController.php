<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Models\EnrollmentPayment;
use App\Services\AutoEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    protected $autoEnrollmentService;

    public function __construct(AutoEnrollmentService $autoEnrollmentService)
    {
        $this->autoEnrollmentService = $autoEnrollmentService;
    }

    /**
     * Get courses data array (private helper)
     */
    private function getCoursesData()
    {
        return [
            [
                'id' => 'maths',
                'name' => 'Mathematics (UK Curriculum)',
                'description' => 'Master essential maths skills aligned with UK Key Stages 1-4',
                'price_ngn' => 20000,
                'price_gbp' => 13.33,
                'type' => 'paid',
                'grades' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                'icon' => '🔢',
            ],
            [
                'id' => 'english',
                'name' => 'English (UK Curriculum)',
                'description' => 'Develop reading, writing, and comprehension skills',
                'price_ngn' => 20000,
                'price_gbp' => 13.33,
                'type' => 'paid',
                'grades' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                'icon' => '📚',
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
                'icon' => '🇳🇬',
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
                'icon' => '🇳🇬',
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
                'icon' => '🇳🇬',
            ],
        ];
    }

    /**
     * Get available courses with pricing (API endpoint)
     */
    public function getCourses()
    {
        return response()->json([
            'success' => true,
            'courses' => $this->getCoursesData(),
        ]);
    }

    /**
     * Calculate pricing based on selected courses
     */
    public function calculatePricing(Request $request)
    {
        $validated = $request->validate([
            'selected_courses' => 'required|array',
            'selected_courses.*' => 'required|string',
            'currency' => 'required|in:NGN,GBP',
        ]);

        $courses = $this->getCoursesData();
        $breakdown = [];
        $subtotal = 0;

        foreach ($validated['selected_courses'] as $courseId) {
            $course = collect($courses)->firstWhere('id', $courseId);
            
            if ($course) {
                $amount = $validated['currency'] === 'NGN' 
                    ? $course['price_ngn'] 
                    : $course['price_gbp'];

                $breakdown[] = [
                    'course' => $courseId,
                    'name' => explode(' ', $course['name'])[0],
                    'amount' => $amount,
                    'is_free' => $course['type'] === 'free',
                    'currency' => $validated['currency'],
                ];

                $subtotal += $amount;
            }
        }

        return response()->json([
            'success' => true,
            'currency' => $validated['currency'],
            'breakdown' => $breakdown,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
        ]);
    }

    /**
     * Get bank account details
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
                    'flag' => '🇳🇬',
                ],
                'gbp' => [
                    'currency' => 'GBP',
                    'bank_name' => 'Monzo/Revolut',
                    'account_number' => '012345678',
                    'account_name' => 'FRICA SOLUTION LIMITED',
                    'flag' => '🇬🇧',
                ],
            ],
            'payment_instructions' => [
                'Use the child\'s name as payment reference',
                'Upload clear photo or PDF of payment receipt',
                'Access is granted immediately upon submission',
                'Admin will verify payment within 24 hours',
            ],
        ]);
    }

    /**
     * Submit complete onboarding with auto-approval
     */
    public function submitOnboarding(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'required|exists:users,id',
            'child_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female',
            'selected_courses' => 'required|array',
            'selected_courses.*' => 'required|string',
            'maths_grade' => 'nullable|integer|min:1|max:10',
            'english_grade' => 'nullable|integer|min:1|max:10',
            'currency' => 'required|in:NGN,GBP',
            'total_amount' => 'required|numeric',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        DB::beginTransaction();
        
        try {
            // 1. Create child user account
            $childEmail = $this->generateChildEmail($validated['child_name']);
            $childPassword = Str::random(12); // Generate random password
            
            $child = User::create([
                'name' => $validated['child_name'],
                'email' => $childEmail,
                'password' => Hash::make($childPassword),
                'role' => 'student',
                'birth_date' => $validated['birth_date'],
                'gender' => $validated['gender'],
                'selected_courses' => json_encode($validated['selected_courses']),
                'maths_grade' => $validated['maths_grade'],
                'english_grade' => $validated['english_grade'],
                'onboarding_completed' => true,
            ]);

            // 2. Link parent-child relationship
            DB::table('parent_child')->insert([
                'parent_id' => $validated['parent_id'],
                'child_id' => $child->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Upload receipt to storage
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                
                // Store in public/receipts directory
                $fileName = time() . '_' . $file->getClientOriginalName();
                $receiptPath = $file->storeAs('receipts', $fileName, 'public');
                
                // Alternatively, if Cloudinary is configured, use it:
                // try {
                //     $uploadResult = cloudinary()->upload($file->getRealPath(), [
                //         'folder' => 'receipts',
                //         'resource_type' => 'auto',
                //     ]);
                //     $receiptPath = $uploadResult->getPublicId();
                // } catch (\Exception $e) {
                //     // Fallback to local storage
                //     $receiptPath = $file->storeAs('receipts', $fileName, 'public');
                // }
            }

            // 4. Create payment record with auto-approval
            $payment = EnrollmentPayment::create([
                'parent_id' => $validated['parent_id'],
                'course_id' => null, // Multi-course enrollment
                'amount' => $validated['total_amount'],
                'currency' => $validated['currency'],
                'receipt_path' => $receiptPath,
                'child_name' => $validated['child_name'],
                'status' => 'temporary_approved', // Auto-approved!
                'auto_approved' => true,
                'includes_maths' => in_array('maths', $validated['selected_courses']),
                'includes_english' => in_array('english', $validated['selected_courses']),
                'includes_yoruba' => in_array('yoruba', $validated['selected_courses']),
                'includes_hausa' => in_array('hausa', $validated['selected_courses']),
                'includes_igbo' => in_array('igbo', $validated['selected_courses']),
            ]);

            // 5. Auto-enroll student in selected courses
            $this->autoEnrollmentService->enrollStudent(
                $child->id,
                $validated['selected_courses'],
                $validated['maths_grade'] ?? null,
                $validated['english_grade'] ?? null
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Child enrolled successfully with immediate access!',
                'child_id' => $child->id,
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Enrollment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate unique email for child based on their name
     */
    private function generateChildEmail($childName)
    {
        $slug = Str::slug($childName);
        $baseEmail = $slug . '@fricalearnstudent.com';
        
        // Check if email exists, add number if needed
        $counter = 1;
        $email = $baseEmail;
        
        while (User::where('email', $email)->exists()) {
            $email = $slug . $counter . '@fricalearnstudent.com';
            $counter++;
        }
        
        return $email;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EnrollmentPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class OnboardingController extends Controller
{
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
                'price_ngn' => 0,
                'price_gbp' => 0,
                'single_price_ngn' => 20000,
                'single_price_gbp' => 10,
                'bundle_price_ngn' => 30000,
                'bundle_price_gbp' => 15,
                'type' => 'paid',
                'trial' => true,
                'trial_label' => '1 Month Free, then ₦20,000 (₦30,000 with both subjects)',
                'grades' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
                'icon' => '🔢',
            ],
            [
                'id' => 'english',
                'name' => 'English (UK Curriculum)',
                'description' => 'Develop reading, writing, and comprehension skills',
                'price_ngn' => 0,
                'price_gbp' => 0,
                'single_price_ngn' => 20000,
                'single_price_gbp' => 10,
                'bundle_price_ngn' => 30000,
                'bundle_price_gbp' => 15,
                'type' => 'paid',
                'trial' => true,
                'trial_label' => '1 Month Free, then ₦20,000 (₦30,000 with both subjects)',
                'grades' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
                'icon' => '📚',
            ],
            [
                'id' => 'yoruba',
                'name' => 'Yoruba Language',
                'description' => 'Connect with Yoruba heritage through language and culture',
                'price_ngn' => 0,
                'price_gbp' => 0,
                'type' => 'free',
                'original_price_ngn' => 30000,
                'original_price_gbp' => 15,
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
                'original_price_ngn' => 30000,
                'original_price_gbp' => 15,
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
                'original_price_ngn' => 30000,
                'original_price_gbp' => 15,
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
        // Decode selected_courses if sent as JSON string (FormData limitation)
        if (is_string($request->input('selected_courses'))) {
            $request->merge([
                'selected_courses' => json_decode($request->input('selected_courses'), true) ?? [],
            ]);
        }

        $validated = $request->validate([
            'parent_id'        => 'required|exists:users,id',
            'child_name'       => 'required|string|max:255',
            'birth_date'       => 'nullable|date',           // optional — child age collected separately
            'gender'           => 'nullable|in:male,female', // optional
            'selected_courses' => 'required|array',
            'selected_courses.*' => 'required|string',
            'maths_grade'      => 'nullable|integer|min:1|max:13',
            'english_grade'    => 'nullable|integer|min:1|max:13',
            'currency'         => 'required|in:NGN,GBP',
            'total_amount'     => 'nullable|numeric',        // 0 for free trial
            'receipt'          => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // optional on free trial
        ]);

        DB::beginTransaction();
        
        try {
            // 1. Create child user account
            $childEmail = $this->generateChildEmail($validated['child_name']);
            $childPassword = Str::random(12); // Generate random password
            
            $child = User::create([
                'name'                 => $validated['child_name'],
                'email'                => $childEmail,
                'password'             => Hash::make($childPassword),
                'role'                 => 'student',
                'birth_date'           => $validated['birth_date'] ?? null,
                'gender'               => $validated['gender'] ?? null,
                'selected_courses'     => json_encode($validated['selected_courses']),
                'maths_grade'          => $validated['maths_grade'] ?? null,
                'english_grade'        => $validated['english_grade'] ?? null,
                'onboarding_completed' => true,
                'is_active'            => true,
                'email_verified_at'    => now(),          // child accounts auto-verified
                'curriculum_region'    => 'uk', // Always UK curriculum (Oak) — currency is just payment preference
                'payment_currency'     => $validated['currency'],
                'trial_ends_at'        => now()->addDays(30), // ✅ 30-day free trial starts NOW
                'is_premium'           => false,
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

            // 4. Create payment record — only when a receipt was uploaded
            //    Free trial path: no receipt, no payment record needed
            $payment = null; // null for free trial path
            if ($receiptPath) {
                $payment = EnrollmentPayment::create([
                    'parent_id'       => $validated['parent_id'],
                    'course_id'       => null,
                    'amount'          => $validated['total_amount'] ?? 0,
                    'currency'        => $validated['currency'],
                    'receipt_path'    => $receiptPath,
                    'child_name'      => $validated['child_name'],
                    'status'          => 'temporary_approved',
                    'auto_approved'   => true,
                    'includes_maths'  => in_array('maths', $validated['selected_courses']),
                    'includes_english'=> in_array('english', $validated['selected_courses']),
                    'includes_yoruba' => in_array('yoruba', $validated['selected_courses']),
                    'includes_hausa'  => in_array('hausa', $validated['selected_courses']),
                    'includes_igbo'   => in_array('igbo', $validated['selected_courses']),
                ]);
            }

            // 5. Auto-enroll student in selected courses
            foreach ($validated['selected_courses'] as $courseId) {
                if ($courseId === 'maths' || $courseId === 'english') {
                    // ── Enrol in the correct Oak subject (IDs 39-47) ──────────
                    // These are the subjects that have all 4,788 Oak lessons.
                    // Map: grade → Oak subject ID
                    $grade = $courseId === 'maths'
                        ? ($validated['maths_grade'] ?? null)
                        : ($validated['english_grade'] ?? null);

                    // Oak subject ID map: [maths_grade => subject_id, english_grade => subject_id]
                    // Subjects 39-47 are the canonical Oak KS1-KS4 subjects with full content
                    $oakSubjectMap = $courseId === 'maths'
                        ? [1=>39, 2=>39, 3=>41, 4=>41, 5=>41, 6=>41, 7=>44, 8=>44, 9=>44, 10=>46, 11=>46, 12=>46, 13=>46]
                        : [1=>40, 2=>40, 3=>42, 4=>42, 5=>42, 6=>42, 7=>45, 8=>45, 9=>45, 10=>47, 11=>47, 12=>47, 13=>47];

                    if ($grade) {
                        $subjectId = $oakSubjectMap[(int)$grade] ?? ($courseId === 'maths' ? 44 : 45);

                        Log::info('Onboarding: Enrolling in Oak subject', [
                            'student_id' => $child->id,
                            'course'     => $courseId,
                            'grade'      => $grade,
                            'subject_id' => $subjectId,
                        ]);
                        
                        // Enroll the student in the external subject
                        DB::table('user_external_subject_enrollments')->insert([
                            'user_id' => $child->id,
                            'external_subject_id' => $subjectId,
                            'progress_percentage' => 0,
                            'enrolled_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        Log::info('Onboarding: Student enrolled in Oak subject', [
                            'student_id' => $child->id,
                            'course'     => $courseId,
                            'grade'      => $grade,
                            'subject_id' => $subjectId,
                        ]);
                    }
                } else {
                    // Language courses - find course and create enrollment
                    $courseName = ucfirst($courseId);
                    $course = DB::table('courses')
                        ->where('title', 'like', "%{$courseName}%")
                        ->first();
                    
                    if ($course) {
                        DB::table('course_enrollments')->insert([
                            'student_id' => $child->id,
                            'course_id' => $course->id,
                            'status' => 'active',
                            'enrolled_at' => now(),
                            'expires_at' => now()->addYear(), // Add 1 year expiry
                        ]);
                    }
                }
            }
            
            // Determine primary learning language from selected courses
            $learningLanguage = 'Yoruba'; // default
            if (in_array('hausa', $validated['selected_courses'])) {
                $learningLanguage = 'Hausa';
            } elseif (in_array('igbo', $validated['selected_courses'])) {
                $learningLanguage = 'Igbo';
            }
            
            // Initialize student profile for week unlocking
            DB::table('student_profiles')->updateOrInsert(
                ['user_id' => $child->id],
                [
                    'current_week' => 1,
                    'week_unlocked_at' => json_encode(['1' => now()->toDateTimeString()]),
                    'learning_language' => $learningLanguage,
                ]
            );

            DB::commit();

            // ── Send enrolment confirmation email to parent ──────────
            try {
                $parent = User::find($validated['parent_id']);
                if ($parent && $parent->email) {
                    $trialEnds   = $child->trial_ends_at
                        ? Carbon::parse($child->trial_ends_at)->format('d F Y')
                        : '30 days from today';
                    $courseNames = [
                        'maths'   => 'Mathematics',
                        'english' => 'English',
                        'yoruba'  => 'Yoruba Language',
                        'hausa'   => 'Hausa Language',
                        'igbo'    => 'Igbo Language',
                    ];
                    $courses = collect($validated['selected_courses'])
                        ->map(function($id) use ($courseNames) {
                            return $courseNames[$id] ?? ucfirst($id);
                        })->join(', ');

                    $html = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                      <div style='background:#2A1650;padding:24px;border-radius:16px 16px 0 0;'>
                        <h1 style='color:#fff;margin:0;font-size:20px;'>Frica<span style='color:#FFFF00;'>Learn</span></h1>
                      </div>
                      <div style='background:#fff;border:1px solid #eee;border-top:none;padding:28px;border-radius:0 0 16px 16px;'>
                        <p>Dear " . e($parent->name) . ",</p>
                        <h2 style='color:#3F2171;font-size:18px;'>🎉 " . e($child->name) . " is enrolled and ready to learn!</h2>
                        <p style='line-height:1.6;'>Your child has been successfully enrolled on FricaLearn.
                        Their 1-month free trial starts today — no payment required until the trial ends.</p>
                        <div style='background:#f3effa;border-radius:12px;padding:16px;margin:16px 0;'>
                          <table width='100%' style='font-size:14px;'>
                            <tr><td style='padding:4px 0;color:#666;'>Student</td><td style='text-align:right;font-weight:bold;'>" . e($child->name) . "</td></tr>
                            <tr><td style='padding:4px 0;color:#666;'>Courses</td><td style='text-align:right;font-weight:bold;'>{$courses}</td></tr>
                            <tr><td style='padding:4px 0;color:#666;'>Free trial ends</td><td style='text-align:right;font-weight:bold;'>{$trialEnds}</td></tr>
                            <tr><td style='padding:4px 0;color:#666;'>AI Tutor</td><td style='text-align:right;'>Available 24/7</td></tr>
                          </table>
                        </div>
                        <p style='line-height:1.6;font-size:13px;color:#555;'>
                          " . e($child->name) . " can start learning immediately. You'll receive weekly feedback
                          emails and a full monthly progress report — so you always know how they're doing.
                        </p>
                        <p style='margin-top:20px;'>
                          <a href='https://fricalearn.com/parent/dashboard'
                             style='background:#3F2171;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:bold;'>
                            Open Parent Dashboard
                          </a>
                        </p>
                        <p style='color:#999;font-size:12px;margin-top:24px;'>
                          FRICA SOLUTION LIMITED · hello@fricalearn.com · WhatsApp +234 817 448 5504
                        </p>
                      </div>
                    </div>";

                    Mail::html($html, function ($m) use ($parent, $child) {
                        $m->to($parent->email)
                          ->subject("🎉 " . $child->name . " is enrolled at FricaLearn — free trial starts today!");
                    });
                }
            } catch (\Exception $mailErr) {
                Log::error('Enrolment confirmation email failed: ' . $mailErr->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Child enrolled successfully with immediate access!',
                'child_id' => $child->id,
                'payment_id' => ($payment !== null) ? $payment->id : null,
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

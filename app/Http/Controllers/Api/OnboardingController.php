<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    // =========================================================
    // COURSES DATA
    // =========================================================

    private function getUkCoursesData(): array
    {
        return [
            [
                'id' => 'maths', 'name' => 'Mathematics (UK Curriculum)',
                'description' => 'Master essential maths skills aligned with UK Key Stages 1-4',
                'price_ngn' => 0, 'price_gbp' => 13.33, 'type' => 'paid',
                'grades' => [1,2,3,4,5,6,7,8,9,10,11],
                'grade_labels' => ['Year 1','Year 2','Year 3','Year 4','Year 5','Year 6','Year 7','Year 8','Year 9','Year 10','Year 11'],
                'curriculum' => 'uk', 'source' => 'Oak National Academy', 'icon' => '🔢',
            ],
            [
                'id' => 'english', 'name' => 'English (UK Curriculum)',
                'description' => 'Develop reading, writing, and comprehension skills',
                'price_ngn' => 0, 'price_gbp' => 13.33, 'type' => 'paid',
                'grades' => [1,2,3,4,5,6,7,8,9,10,11],
                'grade_labels' => ['Year 1','Year 2','Year 3','Year 4','Year 5','Year 6','Year 7','Year 8','Year 9','Year 10','Year 11'],
                'curriculum' => 'uk', 'source' => 'Oak National Academy', 'icon' => '📚',
            ],
            ['id' => 'yoruba', 'name' => 'Yoruba Language', 'description' => 'Connect with Yoruba heritage through language and culture', 'price_ngn' => 0, 'price_gbp' => 0, 'type' => 'free', 'original_price_gbp' => 13.33, 'scholarship' => true, 'curriculum' => 'both', 'icon' => '🇳🇬'],
            ['id' => 'hausa', 'name' => 'Hausa Language', 'description' => 'Learn Hausa language and cultural traditions', 'price_ngn' => 0, 'price_gbp' => 0, 'type' => 'free', 'original_price_gbp' => 13.33, 'scholarship' => true, 'curriculum' => 'both', 'icon' => '🇳🇬'],
            ['id' => 'igbo', 'name' => 'Igbo Language', 'description' => 'Explore Igbo language and heritage', 'price_ngn' => 0, 'price_gbp' => 0, 'type' => 'free', 'original_price_gbp' => 13.33, 'scholarship' => true, 'curriculum' => 'both', 'icon' => '🇳🇬'],
        ];
    }

    private function getNigerianCoursesData(): array
    {
        return [
            [
                'id' => 'maths', 'name' => 'Mathematics',
                'description' => 'Master maths skills from Primary through Junior Secondary',
                'price_ngn' => 20000, 'price_gbp' => 0, 'type' => 'paid',
                'grades' => [1,2,3,4,5,6,7,8,9],
                'grade_labels' => ['Primary 1','Primary 2','Primary 3','Primary 4','Primary 5','Primary 6','JSS 1','JSS 2','JSS 3'],
                'curriculum' => 'nigeria', 'source' => 'FricaLearn', 'icon' => '🔢',
            ],
            [
                'id' => 'english', 'name' => 'English Language',
                'description' => 'Develop reading, writing, and comprehension skills',
                'price_ngn' => 20000, 'price_gbp' => 0, 'type' => 'paid',
                'grades' => [1,2,3,4,5,6,7,8,9],
                'grade_labels' => ['Primary 1','Primary 2','Primary 3','Primary 4','Primary 5','Primary 6','JSS 1','JSS 2','JSS 3'],
                'curriculum' => 'nigeria', 'source' => 'FricaLearn', 'icon' => '📚',
            ],
            ['id' => 'yoruba', 'name' => 'Yoruba Language', 'description' => 'Connect with Yoruba heritage through language and culture', 'price_ngn' => 0, 'price_gbp' => 0, 'type' => 'free', 'original_price_ngn' => 20000, 'scholarship' => true, 'curriculum' => 'both', 'icon' => '🇳🇬'],
            ['id' => 'hausa', 'name' => 'Hausa Language', 'description' => 'Learn Hausa language and cultural traditions', 'price_ngn' => 0, 'price_gbp' => 0, 'type' => 'free', 'original_price_ngn' => 20000, 'scholarship' => true, 'curriculum' => 'both', 'icon' => '🇳🇬'],
            ['id' => 'igbo', 'name' => 'Igbo Language', 'description' => 'Explore Igbo language and heritage', 'price_ngn' => 0, 'price_gbp' => 0, 'type' => 'free', 'original_price_ngn' => 20000, 'scholarship' => true, 'curriculum' => 'both', 'icon' => '🇳🇬'],
        ];
    }

    /**
     * GET /onboarding/courses?currency=NGN|GBP
     */
    public function getCourses(Request $request)
    {
        $currency = $request->query('currency', 'GBP');
        $courses  = $currency === 'NGN' ? $this->getNigerianCoursesData() : $this->getUkCoursesData();

        return response()->json([
            'success'           => true,
            'curriculum_region' => $currency === 'NGN' ? 'nigeria' : 'uk',
            'currency'          => $currency,
            'courses'           => $courses,
        ]);
    }

    // =========================================================
    // CALCULATE PRICING
    // =========================================================

    public function calculatePricing(Request $request)
    {
        $validated = $request->validate([
            'selected_courses'   => 'required|array',
            'selected_courses.*' => 'required|string',
            'currency'           => 'required|in:NGN,GBP',
        ]);

        $courses = $validated['currency'] === 'NGN'
            ? $this->getNigerianCoursesData()
            : $this->getUkCoursesData();

        $breakdown = [];
        $subtotal  = 0;

        foreach ($validated['selected_courses'] as $courseId) {
            $course = collect($courses)->firstWhere('id', $courseId);
            if ($course) {
                $amount = $validated['currency'] === 'NGN' ? $course['price_ngn'] : $course['price_gbp'];
                $breakdown[] = [
                    'course'   => $courseId,
                    'name'     => $course['name'],
                    'amount'   => $amount,
                    'is_free'  => $course['type'] === 'free',
                    'currency' => $validated['currency'],
                ];
                $subtotal += $amount;
            }
        }

        return response()->json([
            'success'   => true,
            'currency'  => $validated['currency'],
            'breakdown' => $breakdown,
            'subtotal'  => $subtotal,
            'discount'  => 0,
            'total'     => $subtotal,
        ]);
    }

    // =========================================================
    // BANK DETAILS
    // =========================================================

    public function getBankDetails()
    {
        return response()->json([
            'success' => true,
            'bank_accounts' => [
                'ngn' => [
                    'currency'       => 'NGN',
                    'bank_name'      => 'PROVIDUS BANK',
                    'account_number' => '1309393680',
                    'account_name'   => 'FRICA SOLUTION LIMITED',
                    'flag'           => '🇳🇬',
                ],
                'gbp' => [
                    'currency'       => 'GBP',
                    'bank_name'      => 'Monzo/Revolut',
                    'account_number' => '012345678',
                    'account_name'   => 'FRICA SOLUTION LIMITED',
                    'flag'           => '🇬🇧',
                ],
            ],
            'payment_instructions' => [
                "Use the child's name as payment reference",
                'Upload clear photo or PDF of payment receipt',
                'Access is granted immediately upon submission',
                'Admin will verify payment within 24 hours',
            ],
        ]);
    }

    // =========================================================
    // SUBMIT ONBOARDING
    // =========================================================

    public function submitOnboarding(Request $request)
    {
        $validated = $request->validate([
            'parent_id'          => 'required|exists:users,id',
            'child_name'         => 'required|string|max:255',
            'age'                => 'required|integer|min:3|max:18',
            'selected_courses'   => 'required|array',
            'selected_courses.*' => 'required|string',
            'maths_grade'        => 'nullable|integer|min:1|max:11',
            'english_grade'      => 'nullable|integer|min:1|max:11',
            'currency'           => 'required|in:NGN,GBP',
            'total_amount'       => 'required|numeric',
            'receipt'            => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $curriculumRegion = $validated['currency'] === 'NGN' ? 'nigeria' : 'uk';

        DB::beginTransaction();

        try {
            // ── 1. Create child user ──────────────────────────────
            $childEmail    = $this->generateChildEmail($validated['child_name']);
            $childPassword = Str::random(12);

            $child = User::create([
                'name'                 => $validated['child_name'],
                'email'                => $childEmail,
                'password'             => Hash::make($childPassword),
                'role'                 => 'student',
                'age'                  => $validated['age'],
                'selected_courses'     => json_encode($validated['selected_courses']),
                'maths_grade'          => $validated['maths_grade'],
                'english_grade'        => $validated['english_grade'],
                'onboarding_completed' => true,
                'curriculum_region'    => $curriculumRegion,
                'payment_currency'     => $validated['currency'],
            ]);

            // ── 2. Link parent-child ──────────────────────────────
            DB::table('parent_child')->insert([
                'parent_id'  => $validated['parent_id'],
                'child_id'   => $child->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ── 3. Upload receipt ─────────────────────────────────
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $file        = $request->file('receipt');
                $fileName    = time() . '_' . $file->getClientOriginalName();
                $receiptPath = $file->storeAs('receipts', $fileName, 'public');
            }

            // ── 4. Payment record ─────────────────────────────────
            $payment = EnrollmentPayment::create([
                'parent_id'        => $validated['parent_id'],
                'course_id'        => null,
                'amount'           => $validated['total_amount'],
                'currency'         => $validated['currency'],
                'receipt_path'     => $receiptPath,
                'child_name'       => $validated['child_name'],
                'status'           => 'temporary_approved',
                'auto_approved'    => true,
                'includes_maths'   => in_array('maths', $validated['selected_courses']),
                'includes_english' => in_array('english', $validated['selected_courses']),
                'includes_yoruba'  => in_array('yoruba', $validated['selected_courses']),
                'includes_hausa'   => in_array('hausa', $validated['selected_courses']),
                'includes_igbo'    => in_array('igbo', $validated['selected_courses']),
            ]);

            // ── 5. Enroll in courses ──────────────────────────────
            // 🌍 BOTH UK and Nigerian students get Oak (UK) content.
            // Nigerian grades map: Primary 1-6 → Year 1-6, JSS 1-3 → Year 7-9
            foreach ($validated['selected_courses'] as $courseId) {

                if ($courseId === 'maths' || $courseId === 'english') {

                    $grade = $courseId === 'maths'
                        ? $validated['maths_grade']
                        : $validated['english_grade'];

                    if (!$grade) continue;

                    // Find the Oak-synced subject by key stage
                    $ksNum  = $this->gradeToKeyStageNum($grade);
                    $ksCode = 'KS' . $ksNum;
                    $oakKeyword = $courseId === 'maths' ? 'Maths' : 'English';

                    $externalSubject = DB::table('external_subjects')
                        ->where('source', 'Oak National Academy')
                        ->where('key_stage', $ksCode)
                        ->where('name', 'like', "%{$oakKeyword}%")
                        ->where('curriculum_region', 'uk')
                        ->first();

                    \Log::info('Onboarding: Oak subject lookup', [
                        'currency'   => $validated['currency'],
                        'grade'      => $grade,
                        'key_stage'  => $ksCode,
                        'keyword'    => $oakKeyword,
                        'found'      => $externalSubject ? $externalSubject->name : 'NOT FOUND',
                        'child_id'   => $child->id,
                    ]);

                    if (!$externalSubject) {
                        \Log::error("Onboarding: No Oak subject found for {$oakKeyword} {$ksCode} — skipping enrollment");
                        continue;
                    }

                    DB::table('user_external_subject_enrollments')->insertOrIgnore([
                        'user_id'             => $child->id,
                        'external_subject_id' => $externalSubject->id,
                        'progress_percentage' => 0,
                        'enrolled_at'         => now(),
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                } else {
                    // ── Language courses ──────────────────────────
                    $courseName = ucfirst($courseId);
                    $course = DB::table('courses')
                        ->where('title', 'like', "%{$courseName}%")
                        ->first();

                    if ($course) {
                        DB::table('course_enrollments')->insert([
                            'student_id'  => $child->id,
                            'course_id'   => $course->id,
                            'status'      => 'active',
                            'enrolled_at' => now(),
                            'expires_at'  => now()->addYear(),
                        ]);
                    }
                }
            }

            // ── 6. Initialize student profile ─────────────────────
            $learningLanguage = 'Yoruba';
            if (in_array('hausa', $validated['selected_courses'])) $learningLanguage = 'Hausa';
            elseif (in_array('igbo', $validated['selected_courses'])) $learningLanguage = 'Igbo';

            DB::table('student_profiles')->updateOrInsert(
                ['user_id' => $child->id],
                [
                    'current_week'      => 1,
                    'week_unlocked_at'  => json_encode(['1' => now()->toDateTimeString()]),
                    'learning_language' => $learningLanguage,
                ]
            );

            DB::commit();

            return response()->json([
                'success'           => true,
                'message'           => 'Child enrolled successfully with immediate access!',
                'child_id'          => $child->id,
                'payment_id'        => $payment->id,
                'curriculum_region' => $curriculumRegion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Onboarding failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Enrollment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /**
     * Maps grade to UK Key Stage.
     * Works for BOTH UK years and Nigerian classes:
     *   UK Year 1-2 / Nigerian Primary 1-2   → KS1
     *   UK Year 3-6 / Nigerian Primary 3-6   → KS2
     *   UK Year 7-9 / Nigerian JSS 1-3       → KS3
     *   UK Year 10-11                        → KS4
     */
    private function gradeToKeyStageNum(int $grade): int
    {
        if ($grade <= 2)  return 1;
        if ($grade <= 6)  return 2;
        if ($grade <= 9)  return 3;
        return 4;
    }

    private function generateChildEmail(string $childName): string
    {
        $slug      = Str::slug($childName);
        $baseEmail = $slug . '@fricalearnstudent.com';
        $counter   = 1;
        $email     = $baseEmail;

        while (User::where('email', $email)->exists()) {
            $email = $slug . $counter . '@fricalearnstudent.com';
            $counter++;
        }

        return $email;
    }
}

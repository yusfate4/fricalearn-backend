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

    /*
    |--------------------------------------------------------------------------
    | Course Catalogue
    |--------------------------------------------------------------------------
    | NGN (Paystack) → Nigerian NERDC curriculum
    | GBP (Stripe)   → UK National Curriculum via Oak API
    |--------------------------------------------------------------------------
    */

    private function getCoursesData(string $currency = 'GBP'): array
    {
        $isNigeria = $currency === 'NGN';

        $coreCourses = $isNigeria
            ? [
                [
                    'id'          => 'maths',
                    'name'        => 'Mathematics (Nigerian Curriculum)',
                    'description' => 'NERDC-aligned maths: numbers, operations, shapes – Primary 1–6, JSS 1–3',
                    'price_ngn'   => 20000,
                    'price_gbp'   => 0,
                    'type'        => 'paid',
                    'curriculum'  => 'nigeria',
                    'grades'      => [1, 2, 3, 4, 5, 6, 7, 8, 9], // Primary 1-6, JSS 1-3
                    'icon'        => '🔢',
                ],
                [
                    'id'          => 'english',
                    'name'        => 'English Language (Nigerian Curriculum)',
                    'description' => 'Reading, writing and comprehension following NERDC framework',
                    'price_ngn'   => 20000,
                    'price_gbp'   => 0,
                    'type'        => 'paid',
                    'curriculum'  => 'nigeria',
                    'grades'      => [1, 2, 3, 4, 5, 6, 7, 8, 9],
                    'icon'        => '📚',
                ],
            ]
            : [
                [
                    'id'          => 'maths',
                    'name'        => 'Mathematics (UK Curriculum)',
                    'description' => 'Master essential maths skills aligned with UK Key Stages 1–4 (Oak National Academy)',
                    'price_ngn'   => 0,
                    'price_gbp'   => 13.33,
                    'type'        => 'paid',
                    'curriculum'  => 'uk',
                    'grades'      => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                    'icon'        => '🔢',
                ],
                [
                    'id'          => 'english',
                    'name'        => 'English (UK Curriculum)',
                    'description' => 'Develop reading, writing, and comprehension skills (Oak National Academy)',
                    'price_ngn'   => 0,
                    'price_gbp'   => 13.33,
                    'type'        => 'paid',
                    'curriculum'  => 'uk',
                    'grades'      => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                    'icon'        => '📚',
                ],
            ];

        // African languages are available to everyone (free scholarship)
        $languages = [
            [
                'id'                  => 'yoruba',
                'name'                => 'Yoruba Language',
                'description'         => 'Connect with Yoruba heritage through language and culture',
                'price_ngn'           => 0,
                'price_gbp'           => 0,
                'type'                => 'free',
                'curriculum'          => 'both',
                'original_price_ngn'  => 20000,
                'original_price_gbp'  => 13.33,
                'scholarship'         => true,
                'icon'                => '🇳🇬',
            ],
            [
                'id'                  => 'hausa',
                'name'                => 'Hausa Language',
                'description'         => 'Learn Hausa language and cultural traditions',
                'price_ngn'           => 0,
                'price_gbp'           => 0,
                'type'                => 'free',
                'curriculum'          => 'both',
                'original_price_ngn'  => 20000,
                'original_price_gbp'  => 13.33,
                'scholarship'         => true,
                'icon'                => '🇳🇬',
            ],
            [
                'id'                  => 'igbo',
                'name'                => 'Igbo Language',
                'description'         => 'Explore Igbo language and heritage',
                'price_ngn'           => 0,
                'price_gbp'           => 0,
                'type'                => 'free',
                'curriculum'          => 'both',
                'original_price_ngn'  => 20000,
                'original_price_gbp'  => 13.33,
                'scholarship'         => true,
                'icon'                => '🇳🇬',
            ],
        ];

        return array_merge($coreCourses, $languages);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/onboarding/courses
    |--------------------------------------------------------------------------
    */

    public function getCourses(Request $request)
    {
        $currency = strtoupper($request->query('currency', 'GBP'));

        return response()->json([
            'success'           => true,
            'curriculum_region' => $currency === 'NGN' ? 'nigeria' : 'uk',
            'courses'           => $this->getCoursesData($currency),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/onboarding/grades
    | Returns the correct grade labels for the chosen currency/curriculum
    |--------------------------------------------------------------------------
    */

    public function getGrades(Request $request)
    {
        $currency = strtoupper($request->query('currency', 'GBP'));

        if ($currency === 'NGN') {
            $grades = [
                ['value' => 1,  'label' => 'Primary 1', 'key_stage' => 'PRIMARY'],
                ['value' => 2,  'label' => 'Primary 2', 'key_stage' => 'PRIMARY'],
                ['value' => 3,  'label' => 'Primary 3', 'key_stage' => 'PRIMARY'],
                ['value' => 4,  'label' => 'Primary 4', 'key_stage' => 'PRIMARY'],
                ['value' => 5,  'label' => 'Primary 5', 'key_stage' => 'PRIMARY'],
                ['value' => 6,  'label' => 'Primary 6', 'key_stage' => 'PRIMARY'],
                ['value' => 7,  'label' => 'JSS 1',     'key_stage' => 'JSS'],
                ['value' => 8,  'label' => 'JSS 2',     'key_stage' => 'JSS'],
                ['value' => 9,  'label' => 'JSS 3',     'key_stage' => 'JSS'],
            ];
        } else {
            $grades = [
                ['value' => 1,  'label' => 'Year 1',  'key_stage' => 'KS1'],
                ['value' => 2,  'label' => 'Year 2',  'key_stage' => 'KS1'],
                ['value' => 3,  'label' => 'Year 3',  'key_stage' => 'KS2'],
                ['value' => 4,  'label' => 'Year 4',  'key_stage' => 'KS2'],
                ['value' => 5,  'label' => 'Year 5',  'key_stage' => 'KS2'],
                ['value' => 6,  'label' => 'Year 6',  'key_stage' => 'KS2'],
                ['value' => 7,  'label' => 'Year 7',  'key_stage' => 'KS3'],
                ['value' => 8,  'label' => 'Year 8',  'key_stage' => 'KS3'],
                ['value' => 9,  'label' => 'Year 9',  'key_stage' => 'KS3'],
                ['value' => 10, 'label' => 'Year 10', 'key_stage' => 'KS4'],
            ];
        }

        return response()->json([
            'success'           => true,
            'currency'          => $currency,
            'curriculum_region' => $currency === 'NGN' ? 'nigeria' : 'uk',
            'grades'            => $grades,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/onboarding/calculate-pricing
    |--------------------------------------------------------------------------
    */

    public function calculatePricing(Request $request)
    {
        $validated = $request->validate([
            'selected_courses'   => 'required|array',
            'selected_courses.*' => 'required|string',
            'currency'           => 'required|in:NGN,GBP',
        ]);

        $courses   = $this->getCoursesData($validated['currency']);
        $breakdown = [];
        $subtotal  = 0;

        foreach ($validated['selected_courses'] as $courseId) {
            $course = collect($courses)->firstWhere('id', $courseId);

            if ($course) {
                $amount = $validated['currency'] === 'NGN'
                    ? $course['price_ngn']
                    : $course['price_gbp'];

                $breakdown[] = [
                    'course'    => $courseId,
                    'name'      => explode(' ', $course['name'])[0],
                    'amount'    => $amount,
                    'is_free'   => $course['type'] === 'free',
                    'currency'  => $validated['currency'],
                ];

                $subtotal += $amount;
            }
        }

        return response()->json([
            'success'    => true,
            'currency'   => $validated['currency'],
            'breakdown'  => $breakdown,
            'subtotal'   => $subtotal,
            'discount'   => 0,
            'total'      => $subtotal,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/onboarding/bank-details
    |--------------------------------------------------------------------------
    */

    public function getBankDetails()
    {
        return response()->json([
            'success'              => true,
            'bank_accounts'        => [
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
                'Use the child\'s name as payment reference',
                'Upload clear photo or PDF of payment receipt',
                'Access is granted immediately upon submission',
                'Admin will verify payment within 24 hours',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/onboarding/submit
    |--------------------------------------------------------------------------
    */

    public function submitOnboarding(Request $request)
    {
        $validated = $request->validate([
            'parent_id'          => 'required|exists:users,id',
            'child_name'         => 'required|string|max:255',
            'birth_date'         => 'required|date',
            'gender'             => 'required|in:male,female',
            'selected_courses'   => 'required|array',
            'selected_courses.*' => 'required|string',
            'maths_grade'        => 'nullable|integer|min:1|max:10',
            'english_grade'      => 'nullable|integer|min:1|max:10',
            'currency'           => 'required|in:NGN,GBP',
            'total_amount'       => 'required|numeric',
            'receipt'            => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // 🌍 Determine curriculum region from payment currency
        $curriculumRegion = $validated['currency'] === 'NGN' ? 'nigeria' : 'uk';

        DB::beginTransaction();

        try {
            // ------------------------------------------------------------------
            // 1. Create child user account
            // ------------------------------------------------------------------
            $childEmail = $this->generateChildEmail($validated['child_name']);

            $child = User::create([
                'name'              => $validated['child_name'],
                'email'             => $childEmail,
                'password'          => Hash::make(Str::random(16)),
                'role'              => 'student',
                'is_active'         => true,
                // 🌍 Set curriculum based on payment currency
                'curriculum_region' => $curriculumRegion,
                'payment_currency'  => $validated['currency'],
            ]);

            \Log::info('Onboarding: Child created', [
                'child_id'          => $child->id,
                'name'              => $child->name,
                'curriculum_region' => $curriculumRegion,
                'payment_currency'  => $validated['currency'],
            ]);

            // ------------------------------------------------------------------
            // 2. Link parent ↔ child
            // ------------------------------------------------------------------
            DB::table('parent_child')->insert([
                'parent_id'  => $validated['parent_id'],
                'child_id'   => $child->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ------------------------------------------------------------------
            // 3. Upload receipt
            // ------------------------------------------------------------------
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $file        = $request->file('receipt');
                $fileName    = time() . '_' . $file->getClientOriginalName();
                $receiptPath = $file->storeAs('receipts', $fileName, 'public');
            }

            // ------------------------------------------------------------------
            // 4. Create payment record
            // ------------------------------------------------------------------
            $payment = EnrollmentPayment::create([
                'parent_id'        => $validated['parent_id'],
                'course_id'        => null,
                'amount'           => $validated['total_amount'],
                'currency'         => $validated['currency'],
                'receipt_path'     => $receiptPath,
                'child_name'       => $validated['child_name'],
                'status'           => 'temporary_approved',
                'auto_approved'    => true,
                'includes_maths'   => in_array('maths',   $validated['selected_courses']),
                'includes_english' => in_array('english', $validated['selected_courses']),
                'includes_yoruba'  => in_array('yoruba',  $validated['selected_courses']),
                'includes_hausa'   => in_array('hausa',   $validated['selected_courses']),
                'includes_igbo'    => in_array('igbo',    $validated['selected_courses']),
            ]);

            // ------------------------------------------------------------------
            // 5. Enroll student in selected courses
            // ------------------------------------------------------------------
            foreach ($validated['selected_courses'] as $courseId) {

                if (in_array($courseId, ['maths', 'english'])) {

                    $subjectName = $courseId === 'maths' ? 'Mathematics' : 'English';
                    $grade       = $courseId === 'maths'
                        ? $validated['maths_grade']
                        : $validated['english_grade'];

                    if (!$grade) {
                        continue;
                    }

                    // Build a human-readable subject name
                    if ($curriculumRegion === 'nigeria') {
                        $gradeLabel    = $this->getNigerianGradeLabel($grade);
                        $fullSubjectName = "{$subjectName} {$gradeLabel}";
                        $keyStageCode  = $this->getNigerianKeyStage($grade);
                        $source        = 'Nigerian Curriculum (NERDC)';
                    } else {
                        $fullSubjectName = "{$subjectName} Year {$grade}";
                        $keyStageCode  = $this->getUkKeyStage($grade);
                        $source        = 'UK National Curriculum (Oak)';
                    }

                    // Resolve grade_level_id from the grade_levels table
                    $gradeLevel = DB::table('grade_levels')
                        ->where('region', $curriculumRegion)
                        ->where('order_index', $grade)
                        ->first();

                    $gradeLevelId = $gradeLevel?->id ?? null;

                    // Find or create the external subject
                    $externalSubject = DB::table('external_subjects')
                        ->where('name', '=', $fullSubjectName)
                        ->where('curriculum_region', $curriculumRegion)
                        ->first();

                    \Log::info('Onboarding: Looking for external subject', [
                        'searching_for'     => $fullSubjectName,
                        'curriculum_region' => $curriculumRegion,
                        'found'             => $externalSubject ? 'YES' : 'NO',
                    ]);

                    if (!$externalSubject) {
                        $subjectId = DB::table('external_subjects')->insertGetId([
                            'name'              => $fullSubjectName,
                            'key_stage'         => $keyStageCode,
                            'year_group'        => $grade,
                            'source'            => $source,
                            'curriculum_region' => $curriculumRegion,
                            'grade_level_id'    => $gradeLevelId,
                            'framework_code'    => $keyStageCode,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);

                        \Log::info('Onboarding: Created external subject', [
                            'id'   => $subjectId,
                            'name' => $fullSubjectName,
                        ]);
                    } else {
                        $subjectId = $externalSubject->id;
                    }

                    // Enroll the student
                    DB::table('user_external_subject_enrollments')->insertOrIgnore([
                        'user_id'             => $child->id,
                        'external_subject_id' => $subjectId,
                        'progress_percentage' => 0,
                        'enrolled_at'         => now(),
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                } else {
                    // Language courses (Yoruba / Hausa / Igbo) → course_enrollments
                    $courseName = ucfirst($courseId);
                    $course     = DB::table('courses')
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

            // ------------------------------------------------------------------
            // 6. Initialise student profile
            // ------------------------------------------------------------------
            $learningLanguage = 'Yoruba';
            if (in_array('hausa', $validated['selected_courses'])) {
                $learningLanguage = 'Hausa';
            } elseif (in_array('igbo', $validated['selected_courses'])) {
                $learningLanguage = 'Igbo';
            }

            DB::table('student_profiles')->updateOrInsert(
                ['user_id' => $child->id],
                [
                    'current_week'       => 1,
                    'week_unlocked_at'   => json_encode(['1' => now()->toDateTimeString()]),
                    'learning_language'  => $learningLanguage,
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

            return response()->json([
                'success' => false,
                'message' => 'Enrollment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

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

    /**
     * Map numeric grade (1–10) to UK Key Stage code
     */
    private function getUkKeyStage(int $grade): string
    {
        return match(true) {
            $grade <= 2  => 'KS1',
            $grade <= 6  => 'KS2',
            $grade <= 9  => 'KS3',
            default      => 'KS4',
        };
    }

    /**
     * Map numeric grade (1–9) to Nigerian framework code
     */
    private function getNigerianKeyStage(int $grade): string
    {
        return match(true) {
            $grade <= 6 => 'PRIMARY',
            default     => 'JSS',
        };
    }

    /**
     * Map numeric grade to Nigerian display label
     */
    private function getNigerianGradeLabel(int $grade): string
    {
        return match($grade) {
            1 => 'Primary 1',
            2 => 'Primary 2',
            3 => 'Primary 3',
            4 => 'Primary 4',
            5 => 'Primary 5',
            6 => 'Primary 6',
            7 => 'JSS 1',
            8 => 'JSS 2',
            9 => 'JSS 3',
            default => "Grade {$grade}",
        };
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OakCurriculumController extends Controller
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.oak.api_url');
        $this->apiKey = config('services.oak.api_key');
    }

    // =========================================================
    // TEST CONNECTION
    // =========================================================

    public function testConnection()
    {
        try {
            $data = $this->get('/key-stages');
            return response()->json([
                'success' => true,
                'message' => 'Oak API connected!',
                'key_stages' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // KEY STAGES
    // GET /key-stages
    // Returns: [{ slug, title }]
    // =========================================================

    public function getKeyStages()
    {
        try {
            $data = $this->get('/key-stages');
            // Field names: slug, title
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Oak: getKeyStages failed - ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // SUBJECTS
    // GET /subjects
    // Returns: [{ subjectTitle, subjectSlug, keyStages[{ keyStageSlug, keyStageTitle }], years[] }]
    // =========================================================

    public function getSubjects(Request $request)
    {
        $keyStageFilter = $request->query('key_stage'); // optional filter e.g. 'ks1'

        try {
            $data = $this->get('/subjects');

            // Filter by key stage if requested
            if ($keyStageFilter) {
                $data = array_filter($data, function ($subject) use ($keyStageFilter) {
                    $ksSlugs = array_column($subject['keyStages'] ?? [], 'keyStageSlug');
                    return in_array($keyStageFilter, $ksSlugs);
                });
                $data = array_values($data); // re-index
            }

            // Return clean array with correct field names
            return response()->json(array_map(fn($s) => [
                'slug'       => $s['subjectSlug'],
                'title'      => $s['subjectTitle'],
                'key_stages' => array_column($s['keyStages'] ?? [], 'keyStageSlug'),
                'years'      => $s['years'] ?? [],
            ], $data));

        } catch (\Exception $e) {
            Log::error('Oak: getSubjects failed - ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // UNITS
    // GET /key-stages/{keyStage}/subject/{subject}/units
    // Returns: [{ yearSlug, yearTitle, units[{ unitSlug, unitTitle }] }]
    // =========================================================

    public function getUnits(Request $request)
    {
        $keyStage = $request->query('key_stage', 'ks1');
        $subject  = $request->query('subject', 'maths');

        try {
            $data = $this->get("/key-stages/{$keyStage}/subject/{$subject}/units");

            // Flatten into a clean list
            $units = [];
            foreach ($data as $yearGroup) {
                foreach ($yearGroup['units'] ?? [] as $unit) {
                    $units[] = [
                        'unit_slug'  => $unit['unitSlug'],
                        'unit_title' => $unit['unitTitle'],
                        'year_slug'  => $yearGroup['yearSlug']  ?? null,
                        'year_title' => $yearGroup['yearTitle'] ?? null,
                        'key_stage'  => $keyStage,
                        'subject'    => $subject,
                    ];
                }
            }

            return response()->json([
                'key_stage' => $keyStage,
                'subject'   => $subject,
                'count'     => count($units),
                'units'     => $units,
            ]);

        } catch (\Exception $e) {
            Log::error('Oak: getUnits failed - ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // LESSONS
    // GET /key-stages/{keyStage}/subject/{subject}/lessons
    // Returns: [{ unitSlug, unitTitle, lessons[{ lessonSlug, lessonTitle }] }]
    // =========================================================

    public function getLessons(Request $request)
    {
        $keyStage = $request->query('key_stage', 'ks1');
        $subject  = $request->query('subject', 'maths');
        $unit     = $request->query('unit'); // optional unit slug filter
        $limit    = $request->query('limit', 10);
        $offset   = $request->query('offset', 0);

        $params = "?limit={$limit}&offset={$offset}";
        if ($unit) $params .= "&unit={$unit}";

        try {
            $data = $this->get("/key-stages/{$keyStage}/subject/{$subject}/lessons{$params}");

            // Flatten into a clean list
            $lessons = [];
            foreach ($data as $unitGroup) {
                foreach ($unitGroup['lessons'] ?? [] as $lesson) {
                    $lessons[] = [
                        'lesson_slug'  => $lesson['lessonSlug'],
                        'lesson_title' => $lesson['lessonTitle'],
                        'unit_slug'    => $unitGroup['unitSlug'],
                        'unit_title'   => $unitGroup['unitTitle'],
                        'key_stage'    => $keyStage,
                        'subject'      => $subject,
                    ];
                }
            }

            return response()->json([
                'key_stage' => $keyStage,
                'subject'   => $subject,
                'count'     => count($lessons),
                'lessons'   => $lessons,
            ]);

        } catch (\Exception $e) {
            Log::error('Oak: getLessons failed - ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // LESSON DETAIL
    // GET /lessons/{lesson}/summary
    // =========================================================

    public function getLessonDetail(Request $request)
    {
        $lessonSlug = $request->query('lesson_slug');

        if (!$lessonSlug) {
            return response()->json(['error' => 'lesson_slug is required'], 422);
        }

        try {
            $data = $this->get("/lessons/{$lessonSlug}/summary");
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Oak: getLessonDetail failed - ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // LESSON QUIZ
    // GET /lessons/{lesson}/quiz
    // Returns: { starterQuiz[{ question, questionType, answers[] }], exitQuiz[] }
    // =========================================================

    public function getLessonQuiz(Request $request)
    {
        $lessonSlug = $request->query('lesson_slug');

        if (!$lessonSlug) {
            return response()->json(['error' => 'lesson_slug is required'], 422);
        }

        try {
            $data = $this->get("/lessons/{$lessonSlug}/quiz");
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Oak: getLessonQuiz failed - ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // SEARCH LESSONS
    // GET /search/lessons?q={term}&keyStage={ks}&subject={subject}
    // =========================================================

    public function searchLessons(Request $request)
    {
        $q        = $request->query('q');
        $keyStage = $request->query('key_stage');
        $subject  = $request->query('subject');

        if (!$q) {
            return response()->json(['error' => 'q (search query) is required'], 422);
        }

        $params = "?q=" . urlencode($q);
        if ($keyStage) $params .= "&keyStage={$keyStage}";
        if ($subject)  $params .= "&subject={$subject}";

        try {
            $data = $this->get("/search/lessons{$params}");
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Oak: searchLessons failed - ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // PRIVATE HTTP HELPER
    // =========================================================

    private function get(string $endpoint): array
    {
        $url = rtrim($this->apiUrl, '/') . $endpoint;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
        ])->timeout(30)->get($url);

        if ($response->status() === 401) {
            throw new \Exception('Oak API: Unauthorized — check your API key in .env');
        }

        if ($response->status() === 404) {
            throw new \Exception("Oak API: Not found — endpoint {$endpoint} does not exist");
        }

        if (!$response->successful()) {
            throw new \Exception("Oak API request failed ({$response->status()}): " . $response->body());
        }

        return $response->json() ?? [];
    }
}
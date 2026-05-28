<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OakCurriculumController extends Controller
{
    private $apiUrl;
    private $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.oak.api_url');
        $this->apiKey = config('services.oak.api_key');
    }

    /**
     * Test Oak API connection
     */
    public function testConnection()
    {
        try {
            $response = $this->makeRequest('/key-stages');
            
            return response()->json([
                'success' => true,
                'message' => 'Oak API connection successful',
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Oak API connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available key stages
     */
    public function getKeyStages()
    {
        try {
            $response = $this->makeRequest('/key-stages');
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Oak API Error - Get Key Stages: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get subjects for a key stage
     */
    public function getSubjects(Request $request)
    {
        $keyStage = $request->query('key_stage', 'ks1');

        try {
            $response = $this->makeRequest("/key-stages/{$keyStage}/subjects");
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Oak API Error - Get Subjects: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get programmes (year groups) for a subject
     */
    public function getProgrammes(Request $request)
    {
        $keyStage = $request->query('key_stage', 'ks1');
        $subject = $request->query('subject', 'maths');

        try {
            $response = $this->makeRequest("/key-stages/{$keyStage}/subjects/{$subject}/programmes");
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Oak API Error - Get Programmes: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get units (topics) for a programme
     */
    public function getUnits(Request $request)
    {
        $programmeSlug = $request->query('programme_slug');

        try {
            $response = $this->makeRequest("/programmes/{$programmeSlug}/units");
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Oak API Error - Get Units: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get lessons for a unit
     */
    public function getLessons(Request $request)
    {
        $unitSlug = $request->query('unit_slug');

        try {
            $response = $this->makeRequest("/units/{$unitSlug}/lessons");
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Oak API Error - Get Lessons: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get lesson details
     */
    public function getLesson(Request $request)
    {
        $lessonSlug = $request->query('lesson_slug');

        try {
            $response = $this->makeRequest("/lessons/{$lessonSlug}");
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Oak API Error - Get Lesson: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Make REST API request to Oak
     */
    private function makeRequest($endpoint)
    {
        $url = $this->apiUrl . $endpoint;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->get($url);

        if (!$response->successful()) {
            throw new \Exception('Oak API request failed: ' . $response->body());
        }

        return $response->json();
    }
}
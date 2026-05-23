<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Oak National Academy API Integration
 * 
 * Fetches UK curriculum content from Oak National Academy
 * Docs: https://open-api.thenational.academy/docs/about-oaks-api/api-overview
 */
class OakCurriculumController extends Controller
{
    private $oakApiUrl = 'https://oak-national.thenational.academy/api/v1';
    
    /**
     * Fetch curriculum for specific key stage and subject
     */
    public function getCurriculum(Request $request)
    {
        $keyStage = $request->input('key_stage', 'ks1'); // ks1, ks2, ks3, ks4
        $subject = $request->input('subject', 'maths'); // maths, english, science, etc.
        
        $query = '
        query GetCurriculum($subjectSlug: String!, $keyStageSlug: String!) {
            units(where: { 
                subject: { slug: $subjectSlug }, 
                keyStage: { slug: $keyStageSlug } 
            }) {
                id
                title
                slug
                description
                order
                lessons {
                    id
                    title
                    slug
                    description
                    videoMuxPlaybackId
                    containsVideo
                    hasWorksheet
                    hasQuiz
                    pupilLessonOutcome
                    lessonEquipment
                    keyLearningPoints
                    order
                }
            }
        }';
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                // Add your Oak API key here when you get it
                // 'Authorization' => 'Bearer ' . config('services.oak.api_key'),
            ])->post($this->oakApiUrl, [
                'query' => $query,
                'variables' => [
                    'subjectSlug' => $subject,
                    'keyStageSlug' => $keyStage
                ]
            ]);
            
            if ($response->failed()) {
                Log::error('Oak API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return response()->json(['error' => 'Failed to fetch Oak curriculum'], 502);
            }
            
            return response()->json($response->json());
            
        } catch (\Exception $e) {
            Log::error('Oak API Exception: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * Get specific lesson details
     */
    public function getLesson(Request $request)
    {
        $lessonSlug = $request->input('lesson_slug');
        
        $query = '
        query GetLesson($lessonSlug: String!) {
            lesson(where: { slug: $lessonSlug }) {
                id
                title
                slug
                description
                videoMuxPlaybackId
                containsVideo
                hasWorksheet
                hasQuiz
                pupilLessonOutcome
                lessonEquipment
                keyLearningPoints
                transcriptSentences
                quizQuestions {
                    question
                    answers
                    correctAnswer
                    hint
                }
            }
        }';
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->oakApiUrl, [
                'query' => $query,
                'variables' => [
                    'lessonSlug' => $lessonSlug
                ]
            ]);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            return response()->json(['error' => 'Lesson not found'], 404);
            
        } catch (\Exception $e) {
            Log::error('Oak API Exception: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * Get all available key stages
     */
    public function getKeyStages()
    {
        return response()->json([
            'key_stages' => [
                ['slug' => 'eyfs', 'name' => 'Early Years Foundation Stage', 'years' => ['Reception']],
                ['slug' => 'ks1', 'name' => 'Key Stage 1', 'years' => ['Year 1', 'Year 2']],
                ['slug' => 'ks2', 'name' => 'Key Stage 2', 'years' => ['Year 3', 'Year 4', 'Year 5', 'Year 6']],
                ['slug' => 'ks3', 'name' => 'Key Stage 3', 'years' => ['Year 7', 'Year 8', 'Year 9']],
                ['slug' => 'ks4', 'name' => 'Key Stage 4', 'years' => ['Year 10', 'Year 11']],
            ]
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PronunciationController extends Controller
{
    public function verify(Request $request, AIService $ai)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a,webm',
            'expected_text' => 'required|string'
        ]);

        // 1. Send Audio to OpenAI Whisper (Speech-to-Text)
        $audioFile = $request->file('audio');
        
        $transcriptionResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->attach('file', file_get_contents($audioFile), 'recording.webm')
          ->post('https://api.openai.com/v1/audio/transcriptions', [
            'model' => 'whisper-1',
            'language' => 'yo' // Yoruba
        ]);

        $transcribedText = $transcriptionResponse->json('text');

        // 2. Score the transcription using our AI Service
        $result = $ai->scorePronunciation($request->expected_text, $transcribedText);
        
        return response()->json(json_decode($result, true));
    }
}
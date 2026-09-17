<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Universal Upload Handler
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // Max 50MB for now
            'type' => 'required|in:avatar,lesson,reward,assignment',
        ]);

        $file = $request->file('file');
        $type = $request->type;

        // 1. Generate a clean, unique filename
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) 
                    . '-' . time() . '.' . $extension;

        // 2. Determine Folder Path
        $path = "public/uploads/{$type}s";

        // 3. Store the file
        $storedPath = $file->storeAs($path, $fileName);

        // 4. Generate the Public URL
        // This converts 'public/uploads/...' to 'http://localhost:8000/storage/uploads/...'
        $url = asset(Storage::url($storedPath));

        return response()->json([
            'message' => 'File uploaded successfully!',
            'url' => $url,
            'file_name' => $fileName,
            'path' => $storedPath
        ], 200);
    }
}
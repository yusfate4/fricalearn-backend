<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    public function uploadVideo($file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('videos', $filename, 'public');
        return Storage::url($path);
    }

    public function uploadDocument($file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('documents', $filename, 'public');
        return Storage::url($path);
    }

    public function uploadAudio($file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('audio', $filename, 'public');
        return Storage::url($path);
    }
    
    public function uploadImage($file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('images', $filename, 'public');
        return Storage::url($path);
    }
}
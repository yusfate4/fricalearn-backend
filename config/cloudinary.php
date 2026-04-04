<?php

return [
    // 1. Try the full URL first
    'cloud_url' => env('CLOUDINARY_URL'),

    // 2. Explicitly map the parts so Laravel doesn't get confused
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key'    => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),

    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET', 'ml_default'),
];
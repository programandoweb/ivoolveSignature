<?php

return [
    'api_key' => env('SIGNATURE_API_KEY'),
    'branding_color' => env('SIGNATURE_BRANDING_COLOR', '#FE4FA2'),
    'storage_disk' => env('SIGNATURE_STORAGE_DISK', 'signatures'),
    'max_upload_size_kb' => (int) env('SIGNATURE_MAX_UPLOAD_SIZE_KB', 20480),
];

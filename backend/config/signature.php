<?php

return [
    'api_key' => env('SIGNATURE_API_KEY'),
    'branding_color' => env('SIGNATURE_BRANDING_COLOR', '#FE4FA2'),
    'storage_disk' => env('SIGNATURE_STORAGE_DISK', 'signatures'),
    'max_upload_size_kb' => (int) env('SIGNATURE_MAX_UPLOAD_SIZE_KB', 20480),
    'whatsapp_endpoint' => env('SIGNATURE_WHATSAPP_ENDPOINT'),
    'whatsapp_timeout' => (int) env('SIGNATURE_WHATSAPP_TIMEOUT', 10),
    'whatsapp_connect_timeout' => (int) env('SIGNATURE_WHATSAPP_CONNECT_TIMEOUT', 5),
    'whatsapp_verify_ssl' => filter_var(env('SIGNATURE_WHATSAPP_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
];

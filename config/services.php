<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brain / external microservices (transcriber, doc-service)
    |--------------------------------------------------------------------------
    */
    'brain' => [
        'transcriber_url' => env('TRANSCRIBER_URL'),
        'transcriber_client_key' => env('TRANSCRIBER_CLIENT_KEY'),
        'doc_service_url' => env('DOC_SERVICE_URL'),
        'hmac_secret' => env('DAGU_HMAC_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Presentation (PPT) microservice (tools/ppt - async job API)
    |--------------------------------------------------------------------------
    | Use X-API-Key; paths: /generate-outline, /generate-content, /export,
    | GET /jobs/{id}/status, GET /jobs/{id}/result.
    */
    'presentation' => [
        'url' => rtrim((string) env('PRESENTATION_MICROSERVICE_URL', ''), '/'),
        'api_key' => env('PRESENTATION_MICROSERVICE_API_KEY'),
        'timeout' => (int) env('PRESENTATION_MICROSERVICE_TIMEOUT', 300),
        'poll_timeout' => (int) env('PRESENTATION_POLL_TIMEOUT', 30),
        'default_slides' => (int) env('PRESENTATION_DEFAULT_SLIDES', 10),
        'outline_path' => '/generate-outline',
        'content_path' => '/generate-content',
        'export_path' => '/export',
        'status_path' => '/jobs/{job_id}/status',
        'result_path' => '/jobs/{job_id}/result',
    ],

    /*
    |--------------------------------------------------------------------------
    | Diagram microservice (tools/diagram)
    |--------------------------------------------------------------------------
    */
    'diagram' => [
        'url' => rtrim((string) env('DIAGRAM_MICROSERVICE_URL', ''), '/'),
        'api_key' => env('DIAGRAM_MICROSERVICE_API_KEY'),
        'timeout' => (int) env('DIAGRAM_MICROSERVICE_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Doc-converter microservice (tools/doc-convertor)
    |--------------------------------------------------------------------------
    */
    'doc_converter' => [
        'url' => rtrim((string) (env('DOC_CONVERTER_URL') ?: env('DOC_CONVERTOR_URL', '')), '/'),
        'api_key' => env('DOC_CONVERTER_API_KEY') ?: env('DOC_CONVERTOR_API_KEY'),
        'timeout' => (int) (env('DOC_CONVERTER_TIMEOUT') ?: env('DOC_CONVERTOR_TIMEOUT', 120)),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Manager (default) and OpenAI (fallback)
    |--------------------------------------------------------------------------
    */
    'ai_manager' => [
        'url' => env('AI_MANAGER_URL'),
        'key' => env('AI_MANAGER_KEY'),
        'model' => env('AI_MANAGER_MODEL', 'deepseek-chat'),
    ],
    'openai' => [
        'url' => env('OPENAI_URL'),
        'key' => env('OPENAI_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chapa payment provider
    |--------------------------------------------------------------------------
    | Get keys from https://dashboard.chapa.co/ (or use test keys for dev).
    */
    'chapa' => [
        'public_key' => env('CHAPA_PUBLIC_KEY', ''),
        'secret_key' => env('CHAPA_SECRET_KEY', ''),
        'base_url' => rtrim((string) env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'), '/'),
        'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
        'require_signature' => (bool) env('CHAPA_REQUIRE_SIGNATURE', false),
    ],
];

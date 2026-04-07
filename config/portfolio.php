<?php

return [
    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'extraction_model' => env('AI_EXTRACTION_MODEL', 'gpt-4.1'),
        'classification_model' => env('AI_CLASSIFICATION_MODEL', 'gpt-4.1'),
        'max_retries' => (int) env('AI_MAX_RETRIES', 2),
        'retry_delay_ms' => (int) env('AI_RETRY_DELAY', 600),
        'classification_batch_size' => (int) env('AI_CLASSIFICATION_BATCH_SIZE', 50),
    ],
    'upload' => [
        'max_file_size_mb' => (int) env('UPLOAD_MAX_FILE_SIZE_MB', 50),
        'max_files_per_submission' => (int) env('UPLOAD_MAX_FILES_PER_SUBMISSION', 20),
        'accepted_extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'csv', 'xlsx', 'xls'],
    ],
    'processing' => [
        'auto_dispatch' => (bool) env('PORTFOLIO_AUTO_DISPATCH', false),
        'queues' => [
            'default' => 'default',
            'extraction' => 'extraction',
            'classification' => 'classification',
        ],
        'timeouts' => [
            'default' => 30,
            'extraction' => 300,
            'classification' => 120,
        ],
        'tries' => [
            'default' => 3,
            'extraction' => 3,
            'classification' => 3,
        ],
    ],
];

<?php

test('.env.example documents the project-specific environment surface', function () {
    $contents = file_get_contents(base_path('.env.example'));

    expect($contents)->not->toBeFalse();

    preg_match_all('/^([A-Z0-9_]+)=/m', (string) $contents, $matches);

    $keys = $matches[1];

    expect($keys)->toContain(
        'AI_PROVIDER',
        'OPENAI_API_KEY',
        'OPENAI_BASE_URL',
        'ANTHROPIC_API_KEY',
        'ANTHROPIC_BASE_URL',
        'AI_EXTRACTION_MODEL',
        'AI_CLASSIFICATION_MODEL',
        'AI_MAX_RETRIES',
        'AI_RETRY_DELAY',
        'AI_CLASSIFICATION_BATCH_SIZE',
        'UPLOAD_MAX_FILE_SIZE_MB',
        'UPLOAD_MAX_FILES_PER_SUBMISSION',
        'PORTFOLIO_AUTO_DISPATCH',
        'REVERB_SERVER',
        'REVERB_SERVER_HOST',
        'REVERB_SERVER_PORT',
        'REVERB_SERVER_PATH',
        'REVERB_MAX_REQUEST_SIZE',
        'REVERB_SCALING_ENABLED',
        'REVERB_SCALING_CHANNEL',
        'REVERB_PULSE_INGEST_INTERVAL',
        'REVERB_TELESCOPE_INGEST_INTERVAL',
        'REVERB_APP_PING_INTERVAL',
        'REVERB_APP_ACTIVITY_TIMEOUT',
        'REVERB_APP_MAX_CONNECTIONS',
        'REVERB_APP_MAX_MESSAGE_SIZE',
        'REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM',
        'REVERB_APP_RATE_LIMITING_ENABLED',
        'REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS',
        'REVERB_APP_RATE_LIMIT_DECAY_SECONDS',
        'REVERB_APP_RATE_LIMIT_TERMINATE',
    );

    expect((string) $contents)->toContain('APP_NAME="Portfolio Analysis"');
    expect((string) $contents)->toContain('APP_LOCALE=pt_BR');
    expect((string) $contents)->toContain('APP_FAKER_LOCALE=pt_BR');
    expect((string) $contents)->toContain('DB_DATABASE=database/database.sqlite');
    expect((string) $contents)->toContain('AI_PROVIDER=openai');
    expect((string) $contents)->toContain('AI_EXTRACTION_MODEL=gpt-4.1');
    expect((string) $contents)->toContain('AI_CLASSIFICATION_MODEL=gpt-4.1');
    expect((string) $contents)->toContain('UPLOAD_MAX_FILE_SIZE_MB=50');
    expect((string) $contents)->toContain('UPLOAD_MAX_FILES_PER_SUBMISSION=20');
    expect((string) $contents)->toContain('PORTFOLIO_AUTO_DISPATCH=false');
});

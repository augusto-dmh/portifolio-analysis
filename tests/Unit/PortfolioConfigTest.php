<?php

test('portfolio upload config matches the supported submission constraints', function () {
    expect(config('portfolio.upload.max_file_size_mb'))->toBe(50);
    expect(config('portfolio.upload.max_files_per_submission'))->toBe(20);
    expect(config('portfolio.upload.rate_limit_per_minute'))->toBe(10);
    expect(config('portfolio.upload.accepted_extensions'))->toBe([
        'pdf',
        'png',
        'jpg',
        'jpeg',
        'csv',
        'xlsx',
        'xls',
    ]);
});

test('portfolio processing config exposes queue names and retry defaults', function () {
    expect(config('portfolio.processing.queues'))->toBe([
        'default' => 'default',
        'extraction' => 'extraction',
        'classification' => 'classification',
    ]);

    expect(config('portfolio.processing.timeouts'))->toBe([
        'default' => 30,
        'extraction' => 300,
        'classification' => 120,
    ]);

    expect(config('portfolio.processing.tries'))->toBe([
        'default' => 3,
        'extraction' => 3,
        'classification' => 3,
    ]);
});

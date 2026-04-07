<?php

test('ai config exposes the supported providers and default driver', function () {
    expect(config('ai.default'))->toBe('openai');

    expect(config('ai.providers.openai'))->toBe([
        'driver' => 'openai',
        'key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_BASE_URL'),
    ]);

    expect(config('ai.providers.anthropic'))->toBe([
        'driver' => 'anthropic',
        'key' => env('ANTHROPIC_API_KEY'),
        'url' => env('ANTHROPIC_BASE_URL'),
    ]);
});

<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Laravel\Ai\Exceptions\AiException;
use RuntimeException;

class AiCircuitBreaker
{
    public function __construct(
        private readonly CacheFactory $cacheFactory,
    ) {}

    public function run(string $operation, callable $callback): mixed
    {
        $cacheKey = $this->cacheKey($operation);

        if ($this->cache()->has($cacheKey)) {
            throw new RuntimeException($this->openCircuitMessage($operation));
        }

        $attempts = max(1, (int) config('portfolio.ai.max_retries', 2) + 1);
        $retryDelayMs = max(0, (int) config('portfolio.ai.retry_delay_ms', 600));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $callback();

                $this->cache()->forget($cacheKey);

                return $result;
            } catch (AiException $exception) {
                $lastException = $exception;

                if ($attempt < $attempts && $retryDelayMs > 0) {
                    usleep($retryDelayMs * 1000);
                }
            }
        }

        $this->cache()->put(
            $cacheKey,
            true,
            now()->addSeconds($this->cooldownSeconds($attempts, $retryDelayMs)),
        );

        throw new RuntimeException(
            $this->exhaustedMessage($operation),
            previous: $lastException,
        );
    }

    private function cache(): Repository
    {
        return $this->cacheFactory->store();
    }

    private function cacheKey(string $operation): string
    {
        return sprintf('portfolio:ai:circuit-open:%s', $operation);
    }

    private function cooldownSeconds(int $attempts, int $retryDelayMs): int
    {
        return max(5, (int) ceil(($attempts * max($retryDelayMs, 1)) / 1000));
    }

    private function openCircuitMessage(string $operation): string
    {
        return sprintf(
            'AI %s is temporarily paused after repeated provider failures. Retry again shortly.',
            $operation,
        );
    }

    private function exhaustedMessage(string $operation): string
    {
        return sprintf(
            'AI %s is temporarily unavailable after repeated provider failures.',
            $operation,
        );
    }
}

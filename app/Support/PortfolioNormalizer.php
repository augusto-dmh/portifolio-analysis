<?php

namespace App\Support;

use Normalizer;

class PortfolioNormalizer
{
    public function normalizeText(string $value): string
    {
        $value = trim($value);
        $value = $this->stripAccents($value);
        $value = preg_replace('/\s+/', ' ', mb_strtoupper($value)) ?? '';

        return trim($value);
    }

    public function extractB3Ticker(string $value): ?string
    {
        $normalized = $this->normalizeText($value);

        if (preg_match('/\b([A-Z]{4}\d{1,2})\b/', $normalized, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function normalizePosition(string $value): ?float
    {
        $sanitized = preg_replace('/[^\d,\.-]/', '', trim($value)) ?? '';

        if ($sanitized === '') {
            return null;
        }

        if (str_contains($sanitized, ',')) {
            $sanitized = str_replace('.', '', $sanitized);
            $sanitized = str_replace(',', '.', $sanitized);
        }

        if (! is_numeric($sanitized)) {
            return null;
        }

        return round((float) $sanitized, 2);
    }

    private function stripAccents(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_D);

            if (is_string($normalized)) {
                return preg_replace('/\pM/u', '', $normalized) ?? $value;
            }
        }

        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    }
}

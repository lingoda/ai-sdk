<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\RateLimit;

final class TokenEstimator extends AbstractTokenEstimator
{
    /**
     * Generic fallback text extraction when no provider-specific estimator is available
     *
     * @param array<string, mixed> $payload
     */
    protected function extractTextFromPayload(array $payload): string
    {
        $text = '';

        // Try common text fields
        $textFields = ['content', 'text', 'message', 'prompt', 'system', 'user', 'assistant'];
        foreach ($textFields as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $text .= ' ' . $payload[$field];
            }
        }

        // Try to extract from arrays of strings
        foreach ($payload as $value) {
            if (is_string($value)) {
                $text .= ' ' . $value;
            }
        }

        return mb_trim($text);
    }
}

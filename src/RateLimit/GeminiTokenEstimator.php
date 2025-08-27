<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

final class GeminiTokenEstimator extends AbstractTokenEstimator
{
    protected function extractTextFromPayload(array $payload): string
    {
        $text = '';

        // Handle Gemini-style contents
        if (isset($payload['contents']) && is_array($payload['contents'])) {
            foreach ($payload['contents'] as $content) {
                if (is_array($content) && isset($content['parts']) && is_array($content['parts'])) {
                    foreach ($content['parts'] as $part) {
                        if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                            $text .= ' ' . $part['text'];
                        }
                    }
                }
            }
        }

        // Handle systemInstruction
        if (isset($payload['systemInstruction']) && is_string($payload['systemInstruction'])) {
            $text .= ' ' . $payload['systemInstruction'];
        }

        // Handle user/assistant/system keys
        foreach (['user', 'assistant', 'system'] as $role) {
            if (isset($payload[$role]) && is_string($payload[$role])) {
                $text .= ' ' . $payload[$role];
            }
        }

        return trim($text);
    }

    protected function getProviderAdjustments(): array
    {
        return [
            'char_divisor' => 4.1,        // Slightly more efficient than OpenAI
            'word_multiplier' => 0.74,    // Gemini efficiency
            'sentence_tokens' => 19,      // Similar to Anthropic
            'efficiency_factor' => 0.97,  // 3% more efficient
        ];
    }
}
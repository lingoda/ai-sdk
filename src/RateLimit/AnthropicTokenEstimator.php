<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\RateLimit;

final class AnthropicTokenEstimator extends AbstractTokenEstimator
{
    protected function extractTextFromPayload(array $payload): string
    {
        $text = '';

        // Handle Anthropic-style content blocks
        if (isset($payload['content']) && is_array($payload['content'])) {
            foreach ($payload['content'] as $contentBlock) {
                if (is_array($contentBlock) && isset($contentBlock['text']) && is_string($contentBlock['text'])) {
                    $text .= ' ' . $contentBlock['text'];
                }
            }
        }

        // Handle simple content field
        if (isset($payload['content']) && is_string($payload['content'])) {
            $text .= ' ' . $payload['content'];
        }

        // Handle messages array (for compatibility)
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            foreach ($payload['messages'] as $message) {
                if (is_array($message) && isset($message['content']) && is_string($message['content'])) {
                    $text .= ' ' . $message['content'];
                }
            }
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
            'char_divisor' => 4.2,        // Slightly more efficient than OpenAI
            'word_multiplier' => 0.73,    // Anthropic tends to be more efficient
            'sentence_tokens' => 19,      // Slightly lower per sentence
            'efficiency_factor' => 0.95,  // 5% more efficient
        ];
    }
}

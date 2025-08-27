<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\RateLimit;

final class OpenAITokenEstimator extends AbstractTokenEstimator
{
    protected function extractTextFromPayload(array $payload): string
    {
        $text = '';

        // Handle OpenAI-style messages
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            foreach ($payload['messages'] as $message) {
                if (is_array($message) && isset($message['content']) && is_string($message['content'])) {
                    $text .= ' ' . $message['content'];
                }
            }
        }

        // Handle system prompts
        if (isset($payload['system']) && is_string($payload['system'])) {
            $text .= ' ' . $payload['system'];
        }

        // Handle user/assistant/system keys
        foreach (['user', 'assistant', 'system'] as $role) {
            if (isset($payload[$role]) && is_string($payload[$role])) {
                $text .= ' ' . $payload[$role];
            }
        }

        return mb_trim($text);
    }
}

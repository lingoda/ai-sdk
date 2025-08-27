<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Enum;

enum AIProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GEMINI = 'gemini';

    public function getName(): string
    {
        return match($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic',
            self::GEMINI => 'Google Gemini',
        };
    }

    /**
     * Get default rate limits for the provider.
     *
     * @return array{requests_per_minute: int, tokens_per_minute: int}
     */
    public function getDefaultRateLimits(): array
    {
        return match($this) {
            self::OPENAI => [
                'requests_per_minute' => 180, // Conservative 90% of 200 RPM
                'tokens_per_minute' => 450000, // Conservative 90% of 500K TPM
            ],
            self::ANTHROPIC => [
                'requests_per_minute' => 100,
                'tokens_per_minute' => 100000,
            ],
            self::GEMINI => [
                'requests_per_minute' => 1000, // Tier 1 limits
                'tokens_per_minute' => 1000000,
            ],
        };
    }
}
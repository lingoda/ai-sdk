<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\Anthropic;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;

/**
 * @see https://www.anthropic.com/news/1m-context Future 1M context capability when publicly available
 */
enum ChatModel: string implements ModelConfigurationInterface
{
    // Claude 4.1 Series (Latest - Recommended)
    case CLAUDE_OPUS_41 = 'claude-opus-4-1-20250805';
    
    // Claude 4.0 Series (Current - Recommended)
    case CLAUDE_OPUS_4 = 'claude-opus-4-20250514';
    case CLAUDE_SONNET_4 = 'claude-sonnet-4-20250514';
    
    // Claude 3.7 Series (Current)
    case CLAUDE_SONNET_37 = 'claude-3-7-sonnet-20250219';
    
    // Claude 3.5 Series (Active)
    case CLAUDE_HAIKU_35 = 'claude-3-5-haiku-20241022';
    
    // Claude 3 Series (Active)
    case CLAUDE_HAIKU_3 = 'claude-3-haiku-20240307';

    public function getId(): string
    {
        return $this->value;
    }

    public function getMaxTokens(): int
    {
        return match ($this) {
            // All active Claude models have 200K context window
            self::CLAUDE_OPUS_41,
            self::CLAUDE_OPUS_4,
            self::CLAUDE_SONNET_4,
            self::CLAUDE_SONNET_37,
            self::CLAUDE_HAIKU_35,
            self::CLAUDE_HAIKU_3 => 200000,
        };
    }

    /**
     * Get maximum output tokens for this model.
     */
    public function getMaxOutputTokens(): int
    {
        return match ($this) {
            self::CLAUDE_OPUS_41,
            self::CLAUDE_OPUS_4 => 32000,
            self::CLAUDE_SONNET_4,
            self::CLAUDE_SONNET_37 => 64000,
            self::CLAUDE_HAIKU_35 => 8192,
            self::CLAUDE_HAIKU_3 => 4096,
        };
    }

    /**
     * Get the capabilities of this model.
     *
     * @return Capability[]
     */
    public function getCapabilities(): array
    {
        return match ($this) {
            // All Claude models support text, tools, vision, and multimodal
            self::CLAUDE_OPUS_41,
            self::CLAUDE_OPUS_4,
            self::CLAUDE_SONNET_4,
            self::CLAUDE_SONNET_37,
            self::CLAUDE_HAIKU_35,
            self::CLAUDE_HAIKU_3 => [
                Capability::TEXT,
                Capability::TOOLS,
                Capability::VISION,
                Capability::MULTIMODAL,
            ],
        };
    }

    /**
     * Check if this model supports a specific capability.
     */
    public function hasCapability(Capability $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * Get default options for this model.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return match ($this) {
            // API verified: 32,000 max tokens
            self::CLAUDE_OPUS_41,
            self::CLAUDE_OPUS_4 => [
                'temperature' => 0.7,
                'max_tokens' => 32000,
                'top_p' => 1.0,
            ],
            // API verified: Claude Sonnet 4 = 64,000+, Claude 3.7 Sonnet = 64,000+
            self::CLAUDE_SONNET_4,
            self::CLAUDE_SONNET_37 => [
                'temperature' => 0.7,
                'max_tokens' => 64000,
                'top_p' => 1.0,
            ],
            // API verified: Claude 3.5 Haiku = 8,192, Claude 3 Haiku = 4,096
            self::CLAUDE_HAIKU_35 => [
                'temperature' => 0.7,
                'max_tokens' => 8192,
                'top_p' => 1.0,
            ],
            self::CLAUDE_HAIKU_3 => [
                'temperature' => 0.7,
                'max_tokens' => 4096,
                'top_p' => 1.0,
            ],
        };
    }

    /**
     * Get the display name of the model.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::CLAUDE_OPUS_41 => 'Claude Opus 4.1',
            self::CLAUDE_OPUS_4 => 'Claude Opus 4',
            self::CLAUDE_SONNET_4 => 'Claude Sonnet 4',
            self::CLAUDE_SONNET_37 => 'Claude Sonnet 3.7',
            self::CLAUDE_HAIKU_35 => 'Claude Haiku 3.5',
            self::CLAUDE_HAIKU_3 => 'Claude Haiku 3',
        };
    }

    /**
     * Get the default model ID for Anthropic provider.
     * Returns cost-effective Claude Haiku 3.5 as default.
     */
    public function getDefaultModel(): string
    {
        return self::CLAUDE_HAIKU_35->value;
    }
}

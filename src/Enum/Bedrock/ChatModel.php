<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\Bedrock;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;

/**
 * Bedrock base model ids. The client prefixes them with the region's inference profile (eu. or us.).
 */
enum ChatModel: string implements ModelConfigurationInterface
{
    case NOVA_2_LITE = 'amazon.nova-2-lite-v1:0';
    case CLAUDE_HAIKU_45 = 'anthropic.claude-haiku-4-5-20251001-v1:0';

    public function getId(): string
    {
        return $this->value;
    }

    public function getMaxTokens(): int
    {
        return match ($this) {
            self::NOVA_2_LITE => 1000000,
            self::CLAUDE_HAIKU_45 => 200000,
        };
    }

    /**
     * @return Capability[]
     */
    public function getCapabilities(): array
    {
        // No TOOLS: BedrockClient does not convert tool definitions to Symfony AI tools yet
        return [
            Capability::TEXT,
            Capability::VISION,
            Capability::DOCUMENT,
        ];
    }

    public function hasCapability(Capability $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * Bedrock Claude rejects requests without max_tokens.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return ['max_tokens' => 4096];
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::NOVA_2_LITE => 'Amazon Nova 2 Lite',
            self::CLAUDE_HAIKU_45 => 'Claude Haiku 4.5 (Bedrock)',
        };
    }

    public function getDefaultModel(): string
    {
        return self::NOVA_2_LITE->value;
    }

    /**
     * Model name in the Symfony AI Bedrock model catalog.
     *
     * @internal Follows Symfony AI's catalog (0.x), may change with any Symfony AI bump
     *
     * @return non-empty-string
     */
    public function catalogName(): string
    {
        return match ($this) {
            self::NOVA_2_LITE => 'nova-2-lite',
            self::CLAUDE_HAIKU_45 => 'claude-haiku-4-5-20251001',
        };
    }

    /**
     * @internal Transport detail for BedrockClient
     */
    public function apiFormat(): ApiFormat
    {
        return match ($this) {
            self::NOVA_2_LITE => ApiFormat::CONVERSE,
            self::CLAUDE_HAIKU_45 => ApiFormat::ANTHROPIC_MESSAGES,
        };
    }

    /**
     * Whether a JSON-schema response_format is supported.
     *
     * @internal Transport detail for BedrockClient
     */
    public function supportsResponseFormat(): bool
    {
        return match ($this) {
            self::CLAUDE_HAIKU_45 => true,
            self::NOVA_2_LITE => false,
        };
    }

    /**
     * Whether the conversation must open with the user turn.
     *
     * @internal Transport detail for BedrockClient
     */
    public function requiresLeadingUserTurn(): bool
    {
        return match ($this) {
            self::NOVA_2_LITE => true,
            self::CLAUDE_HAIKU_45 => false,
        };
    }
}

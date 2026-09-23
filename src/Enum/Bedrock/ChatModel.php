<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\Bedrock;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;
use Lingoda\AiSdk\Prompt\Attachment;

/**
 * Bedrock base model ids. The client prefixes them with the region's inference profile (eu. or us.).
 */
enum ChatModel: string implements ModelConfigurationInterface
{
    // Amazon Nova
    case NOVA_MICRO = 'amazon.nova-micro-v1:0';
    case NOVA_LITE = 'amazon.nova-lite-v1:0';
    case NOVA_PRO = 'amazon.nova-pro-v1:0';
    case NOVA_2_LITE = 'amazon.nova-2-lite-v1:0';

    // Anthropic Claude
    case CLAUDE_HAIKU_45 = 'anthropic.claude-haiku-4-5-20251001-v1:0';
    case CLAUDE_SONNET_45 = 'anthropic.claude-sonnet-4-5-20250929-v1:0';
    case CLAUDE_OPUS_45 = 'anthropic.claude-opus-4-5-20251101-v1:0';
    case CLAUDE_SONNET_46 = 'anthropic.claude-sonnet-4-6';
    case CLAUDE_OPUS_46 = 'anthropic.claude-opus-4-6-v1';
    case CLAUDE_OPUS_47 = 'anthropic.claude-opus-4-7';
    case CLAUDE_OPUS_48 = 'anthropic.claude-opus-4-8';
    case CLAUDE_SONNET_5 = 'anthropic.claude-sonnet-5';
    case CLAUDE_OPUS_5 = 'anthropic.claude-opus-5';
    case CLAUDE_OPUS_55 = 'anthropic.claude-opus-5-5';

    public function getId(): string
    {
        return $this->value;
    }

    public function getMaxTokens(): int
    {
        return match ($this) {
            self::NOVA_MICRO => 128000,
            self::NOVA_LITE, self::NOVA_PRO => 300000,
            self::NOVA_2_LITE => 1000000,
            self::CLAUDE_HAIKU_45, self::CLAUDE_SONNET_45, self::CLAUDE_OPUS_45, self::CLAUDE_SONNET_46,
            self::CLAUDE_OPUS_46, self::CLAUDE_OPUS_47, self::CLAUDE_OPUS_48, self::CLAUDE_SONNET_5,
            self::CLAUDE_OPUS_5, self::CLAUDE_OPUS_55 => 200000,
        };
    }

    /**
     * @return Capability[]
     */
    public function getCapabilities(): array
    {
        // No TOOLS: BedrockClient does not convert tool definitions to Symfony AI tools yet
        return $this === self::NOVA_MICRO
            ? [Capability::TEXT]
            : [Capability::TEXT, Capability::VISION, Capability::DOCUMENT];
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
            self::NOVA_MICRO => 'Amazon Nova Micro',
            self::NOVA_LITE => 'Amazon Nova Lite',
            self::NOVA_PRO => 'Amazon Nova Pro',
            self::NOVA_2_LITE => 'Amazon Nova 2 Lite',
            self::CLAUDE_HAIKU_45 => 'Claude Haiku 4.5 (Bedrock)',
            self::CLAUDE_SONNET_45 => 'Claude Sonnet 4.5 (Bedrock)',
            self::CLAUDE_OPUS_45 => 'Claude Opus 4.5 (Bedrock)',
            self::CLAUDE_SONNET_46 => 'Claude Sonnet 4.6 (Bedrock)',
            self::CLAUDE_OPUS_46 => 'Claude Opus 4.6 (Bedrock)',
            self::CLAUDE_OPUS_47 => 'Claude Opus 4.7 (Bedrock)',
            self::CLAUDE_OPUS_48 => 'Claude Opus 4.8 (Bedrock)',
            self::CLAUDE_SONNET_5 => 'Claude Sonnet 5 (Bedrock)',
            self::CLAUDE_OPUS_5 => 'Claude Opus 5 (Bedrock)',
            self::CLAUDE_OPUS_55 => 'Claude Opus 5.5 (Bedrock)',
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
            self::NOVA_MICRO => 'nova-micro',
            self::NOVA_LITE => 'nova-lite',
            self::NOVA_PRO => 'nova-pro',
            self::NOVA_2_LITE => 'nova-2-lite',
            self::CLAUDE_HAIKU_45 => 'claude-haiku-4-5-20251001',
            self::CLAUDE_SONNET_45 => 'claude-sonnet-4-5-20250929',
            self::CLAUDE_OPUS_45 => 'claude-opus-4-5-20251101',
            self::CLAUDE_SONNET_46 => 'claude-sonnet-4-6',
            self::CLAUDE_OPUS_46 => 'claude-opus-4-6',
            self::CLAUDE_OPUS_47 => 'claude-opus-4-7',
            self::CLAUDE_OPUS_48 => 'claude-opus-4-8',
            self::CLAUDE_SONNET_5 => 'claude-sonnet-5',
            self::CLAUDE_OPUS_5 => 'claude-opus-5',
            self::CLAUDE_OPUS_55 => 'claude-opus-5-5',
        };
    }

    /**
     * @internal Transport detail for BedrockClient
     */
    public function apiFormat(): ApiFormat
    {
        return match ($this) {
            self::NOVA_MICRO, self::NOVA_LITE, self::NOVA_PRO, self::NOVA_2_LITE => ApiFormat::CONVERSE,
            self::CLAUDE_HAIKU_45, self::CLAUDE_SONNET_45, self::CLAUDE_OPUS_45, self::CLAUDE_SONNET_46,
            self::CLAUDE_OPUS_46, self::CLAUDE_OPUS_47, self::CLAUDE_OPUS_48, self::CLAUDE_SONNET_5,
            self::CLAUDE_OPUS_5, self::CLAUDE_OPUS_55 => ApiFormat::ANTHROPIC_MESSAGES,
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
            self::CLAUDE_HAIKU_45, self::CLAUDE_SONNET_45, self::CLAUDE_OPUS_45, self::CLAUDE_SONNET_46,
            self::CLAUDE_OPUS_46 => true,
            self::NOVA_MICRO, self::NOVA_LITE, self::NOVA_PRO, self::NOVA_2_LITE,
            self::CLAUDE_OPUS_47, self::CLAUDE_OPUS_48, self::CLAUDE_SONNET_5, self::CLAUDE_OPUS_5,
            self::CLAUDE_OPUS_55 => false,
        };
    }

    /**
     * Whether the model accepts a temperature; the newest Claude models reject it.
     *
     * @internal Transport detail for BedrockClient
     */
    public function supportsTemperature(): bool
    {
        return match ($this) {
            self::CLAUDE_OPUS_47, self::CLAUDE_OPUS_48, self::CLAUDE_SONNET_5, self::CLAUDE_OPUS_5,
            self::CLAUDE_OPUS_55 => false,
            self::NOVA_MICRO, self::NOVA_LITE, self::NOVA_PRO, self::NOVA_2_LITE, self::CLAUDE_HAIKU_45,
            self::CLAUDE_SONNET_45, self::CLAUDE_OPUS_45, self::CLAUDE_SONNET_46, self::CLAUDE_OPUS_46 => true,
        };
    }

    /**
     * Whether the model reads a document of this mime type (PDF, DOCX); text and image attachments are separate.
     *
     * @internal Transport detail for BedrockClient
     */
    public function acceptsDocument(string $mimeType): bool
    {
        if (!$this->hasCapability(Capability::DOCUMENT)) {
            return false;
        }

        return match ($this->apiFormat()) {
            ApiFormat::CONVERSE => in_array($mimeType, [Attachment::PDF, Attachment::DOCX], true),
            ApiFormat::ANTHROPIC_MESSAGES => $mimeType === Attachment::PDF,
        };
    }

    /**
     * Whether the conversation must open with the user turn.
     *
     * @internal Transport detail for BedrockClient
     */
    public function requiresLeadingUserTurn(): bool
    {
        return $this->apiFormat() === ApiFormat::CONVERSE;
    }
}

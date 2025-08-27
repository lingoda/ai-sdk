<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Enum\Gemini;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;

enum ChatModel: string implements ModelConfigurationInterface
{
    case GEMINI_2_5_PRO = 'gemini-2.5-pro';
    case GEMINI_2_5_FLASH = 'gemini-2.5-flash';

    public function getId(): string
    {
        return $this->value;
    }

    public function getMaxTokens(): int
    {
        return match($this) {
            self::GEMINI_2_5_PRO,
            self::GEMINI_2_5_FLASH => 1000000,
        };
    }

    /**
     * Get the capabilities of this model.
     *
     * @return Capability[]
     */
    public function getCapabilities(): array
    {
        return match($this) {
            // All Gemini 2.5 models support text, tools, vision, and multimodal
            self::GEMINI_2_5_PRO,
            self::GEMINI_2_5_FLASH => [
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
        return in_array($capability, $this->getCapabilities());
    }

    /**
     * Get default options for this model.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return match($this) {
            self::GEMINI_2_5_PRO,
            self::GEMINI_2_5_FLASH => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1000000,
                'topP' => 0.95,
                'topK' => 40,
            ],
        };
    }

    /**
     * Get the display name of the model.
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::GEMINI_2_5_PRO => 'Gemini 2.5 Pro',
            self::GEMINI_2_5_FLASH => 'Gemini 2.5 Flash',
        };
    }

    /**
     * Get the default model ID for Gemini provider.
     * Returns efficient Gemini 2.5 Flash as default.
     */
    public function getDefaultModel(): string
    {
        return self::GEMINI_2_5_FLASH->value;
    }
}
<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\Gemini;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;

enum ChatModel: string implements ModelConfigurationInterface
{
    // Deprecated by Google: from 1 December 2026 its requests are served by gemini-3.8-flash
    case GEMINI_2_5_PRO = 'gemini-2.5-pro';
    // Deprecated by Google: from 1 December 2026 its requests are served by gemini-3.5-flash-lite
    case GEMINI_2_5_FLASH = 'gemini-2.5-flash';
    case GEMINI_3_1_FLASH_LITE = 'gemini-3.1-flash-lite';
    case GEMINI_3_5_FLASH_LITE = 'gemini-3.5-flash-lite';
    case GEMINI_3_8_FLASH = 'gemini-3.8-flash';
    case GEMINI_4_HYDROGEN = 'gemini-4-hydrogen';
    case GEMINI_4_ARGON = 'gemini-4-argon';
    case GEMINI_4_CARBON = 'gemini-4-carbon';

    public function getId(): string
    {
        return $this->value;
    }

    public function getMaxTokens(): int
    {
        return match ($this) {
            self::GEMINI_2_5_PRO,
            self::GEMINI_2_5_FLASH,
            self::GEMINI_3_1_FLASH_LITE => 1000000,
            // The documented input limit. On Gemini 4 input, thinking and output share it.
            self::GEMINI_3_5_FLASH_LITE,
            self::GEMINI_3_8_FLASH,
            self::GEMINI_4_HYDROGEN,
            self::GEMINI_4_ARGON,
            self::GEMINI_4_CARBON => 1048576,
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
            // All Gemini models support text, tools, vision, and multimodal
            self::GEMINI_2_5_PRO,
            self::GEMINI_2_5_FLASH,
            self::GEMINI_3_1_FLASH_LITE => [
                Capability::TEXT,
                Capability::TOOLS,
                Capability::VISION,
                Capability::DOCUMENT,
                Capability::MULTIMODAL,
            ],
            // These models think before they answer, at a level the API lets the caller choose
            self::GEMINI_3_5_FLASH_LITE,
            self::GEMINI_3_8_FLASH,
            self::GEMINI_4_HYDROGEN,
            self::GEMINI_4_ARGON,
            self::GEMINI_4_CARBON => [
                Capability::TEXT,
                Capability::TOOLS,
                Capability::VISION,
                Capability::DOCUMENT,
                Capability::MULTIMODAL,
                Capability::REASONING,
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
            self::GEMINI_2_5_PRO,
            self::GEMINI_2_5_FLASH => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1000000,
                'topP' => 0.95,
                'topK' => 40,
            ],
            // Documented output limit is 65,536 tokens (input context is 1M)
            // https://ai.google.dev/gemini-api/docs/models/gemini-3.1-flash-lite
            self::GEMINI_3_1_FLASH_LITE => [
                'temperature' => 0.7,
                'maxOutputTokens' => 65536,
                'topP' => 0.95,
                'topK' => 40,
            ],
            // No sampling defaults: Gemini 3.5 and later ignore temperature, topP and topK, and
            // Google has announced that a later generation will reject them.
            // 65,536 is the documented output limit of one response. On Gemini 4 the thinking
            // tokens count towards it too.
            // https://aistudio.google.com/docs/whats-new-gemini-3.6
            // https://aistudio.google.com/docs/whats-new-gemini-4-argon
            self::GEMINI_3_5_FLASH_LITE,
            self::GEMINI_3_8_FLASH,
            self::GEMINI_4_HYDROGEN,
            self::GEMINI_4_ARGON,
            self::GEMINI_4_CARBON => [
                'maxOutputTokens' => 65536,
            ],
        };
    }

    /**
     * Get the display name of the model.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::GEMINI_2_5_PRO => 'Gemini 2.5 Pro',
            self::GEMINI_2_5_FLASH => 'Gemini 2.5 Flash',
            self::GEMINI_3_1_FLASH_LITE => 'Gemini 3.1 Flash Lite',
            self::GEMINI_3_5_FLASH_LITE => 'Gemini 3.5 Flash Lite',
            self::GEMINI_3_8_FLASH => 'Gemini 3.8 Flash',
            self::GEMINI_4_HYDROGEN => 'Gemini 4 Hydrogen',
            self::GEMINI_4_ARGON => 'Gemini 4 Argon',
            self::GEMINI_4_CARBON => 'Gemini 4 Carbon',
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
